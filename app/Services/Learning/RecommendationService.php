<?php

namespace App\Services\Learning;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Recommendation;
use App\Models\StudentLessonProgress;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Đề xuất học tập cá nhân hoá (§11):
 * Phát hiện điểm yếu → Tìm kiến thức nền → Đề xuất bài học → Đề xuất bài tập → Tăng dần độ khó → Kiểm tra lại.
 *
 * Đây là thuật toán quy tắc trên dữ liệu làm bài thật (mastery, lịch sử), không gọi LLM:
 * miễn phí, chạy sau mỗi lần nộp bài, và giải thích được "vì sao gợi ý bài này".
 */
class RecommendationService
{
    /** Số chủ đề yếu tối đa xử lý mỗi lần — học sinh không cần 20 gợi ý cùng lúc. */
    private const MAX_WEAK_TOPICS = 3;

    /** Đạt mức này ở một chủ đề từng làm → đề nghị kiểm tra lại. */
    private const RECHECK_MASTERY = 75;

    private const TTL_DAYS = 7;

    /** @return Collection<int, Recommendation> */
    public function current(User $student, int $limit = 5): Collection
    {
        $items = $this->activeQuery($student)->limit($limit)->get();

        if ($items->isEmpty()) {
            $this->refresh($student);
            $items = $this->activeQuery($student)->limit($limit)->get();
        }

        return $items;
    }

    /** Tính lại toàn bộ đề xuất. Đề xuất đã "xong" giữ lại làm lịch sử. */
    public function refresh(User $student): void
    {
        $completedLessons = StudentLessonProgress::query()
            ->where('user_id', $student->id)
            ->where('status', StudentLessonProgress::STATUS_COMPLETED)
            ->pluck('lesson_id')
            ->flip();

        $mastery = StudentTopicMastery::query()
            ->where('user_id', $student->id)
            ->with('topic')
            ->get()
            ->keyBy('topic_id');

        $items = collect();
        $priority = 1000;

        // 1. Phát hiện điểm yếu.
        $weak = $mastery
            ->filter(fn ($m) => $m->mastery_score < StudentTopicMastery::WEAK_THRESHOLD
                && ($m->correct_count + $m->wrong_count) >= MasteryService::MIN_ATTEMPTS_FOR_CONFIDENCE)
            ->sortBy('mastery_score')
            ->take(self::MAX_WEAK_TOPICS);

        foreach ($weak as $m) {
            // 2. Tìm kiến thức nền: chủ đề đứng trước trong cùng chương mà cũng yếu hoặc chưa từng luyện.
            $prerequisite = $this->prerequisiteOf($m->topic);
            if ($prerequisite && $this->needsWork($mastery->get($prerequisite->id))) {
                if ($lesson = $this->nextLesson($prerequisite, $completedLessons)) {
                    $items->push($this->make($student, Recommendation::TYPE_REVIEW_LESSON, $prerequisite, 'lesson', $lesson->id, null, $priority--,
                        "Ôn kiến thức nền «{$prerequisite->name}» trước — nó là nền cho «{$m->topic->name}»."));
                }
            }

            // 3. Đề xuất bài học của chính chủ đề yếu.
            if ($lesson = $this->nextLesson($m->topic, $completedLessons)) {
                $items->push($this->make($student, Recommendation::TYPE_REVIEW_LESSON, $m->topic, 'lesson', $lesson->id, null, $priority--,
                    "Em đang nắm «{$m->topic->name}» ở mức {$m->mastery_score}% — xem lại bài «{$lesson->title}»."));
            }

            // 4–5. Đề xuất bài tập, bắt đầu từ độ khó vừa sức.
            $difficulty = $m->mastery_score < 40 ? 'easy' : 'medium';
            $items->push($this->make($student, Recommendation::TYPE_PRACTICE_TOPIC, $m->topic, 'topic', $m->topic_id, $difficulty, $priority--,
                'Luyện thêm «'.$m->topic->name.'» mức '.($difficulty === 'easy' ? 'Dễ' : 'Trung bình').' cho chắc tay.'));
        }

        // 5. Tăng dần độ khó: chủ đề đã khá (60–74%) → thử mức Khó.
        $mastery
            ->filter(fn ($m) => $m->mastery_score >= StudentTopicMastery::WEAK_THRESHOLD && $m->mastery_score < self::RECHECK_MASTERY)
            ->sortByDesc('mastery_score')
            ->take(2)
            ->each(function ($m) use ($student, $items, &$priority) {
                $items->push($this->make($student, Recommendation::TYPE_PRACTICE_TOPIC, $m->topic, 'topic', $m->topic_id, 'hard', $priority--,
                    "Em đã khá ở «{$m->topic->name}» ({$m->mastery_score}%) — thử sức với câu Khó."));
            });

        // 6. Kiểm tra lại: chủ đề đã vững → làm một đề có chủ đề đó mà em chưa làm.
        foreach ($mastery->filter(fn ($m) => $m->mastery_score >= self::RECHECK_MASTERY)->take(2) as $m) {
            if ($exam = $this->untakenExamFor($student, $m->topic)) {
                $items->push($this->make($student, Recommendation::TYPE_TAKE_EXAM, $m->topic, 'exam', $exam->id, null, $priority--,
                    "Em đã vững «{$m->topic->name}» ({$m->mastery_score}%) — làm đề «{$exam->title}» để kiểm tra lại."));
            }
        }

        // Học sinh mới chưa có dữ liệu: gợi ý bài đầu tiên chưa học của lớp mình.
        if ($items->isEmpty() && $mastery->isEmpty()) {
            if ($lesson = $this->firstLessonForGrade($student, $completedLessons)) {
                $items->push($this->make($student, Recommendation::TYPE_REVIEW_LESSON, $lesson->topic, 'lesson', $lesson->id, null, $priority--,
                    "Bắt đầu với bài «{$lesson->title}»."));
            }
        }

        DB::transaction(function () use ($student, $items) {
            Recommendation::where('user_id', $student->id)->where('status', '!=', 'done')->delete();

            // Một mục tiêu chỉ gợi ý một lần (kiến thức nền của hai chủ đề có thể trùng).
            $items->unique(fn ($r) => $r['type'].':'.$r['target_type'].':'.$r['target_id'].':'.$r['difficulty'])
                ->each(fn ($r) => Recommendation::create($r));
        });
    }

    /** Đường dẫn để bấm vào một đề xuất. */
    public function urlFor(Recommendation $rec): ?string
    {
        return match ($rec->type) {
            Recommendation::TYPE_REVIEW_LESSON => ($slug = Lesson::whereKey($rec->target_id)->value('slug'))
                ? route('student.lesson.show', $slug) : null,
            Recommendation::TYPE_TAKE_EXAM => ($slug = Exam::whereKey($rec->target_id)->value('slug'))
                ? route('student.exams.show', $slug) : null,
            default => null, // luyện tập là POST form, view tự dựng
        };
    }

    private function activeQuery(User $student)
    {
        return Recommendation::query()
            ->where('user_id', $student->id)
            ->active()
            ->with('topic')
            ->orderByDesc('priority');
    }

    private function prerequisiteOf(Topic $topic): ?Topic
    {
        return Topic::query()
            ->where('chapter_id', $topic->chapter_id)
            ->where('sort_order', '<', $topic->sort_order)
            ->whereHas('lessons', fn ($q) => $q->published())
            ->orderByDesc('sort_order')
            ->first();
    }

    private function needsWork(?StudentTopicMastery $m): bool
    {
        return $m === null || $m->mastery_score < StudentTopicMastery::WEAK_THRESHOLD;
    }

    /** Bài chưa học đầu tiên; học hết rồi thì bài đầu để đọc lại. */
    private function nextLesson(Topic $topic, Collection $completed): ?Lesson
    {
        $lessons = Lesson::query()->published()->where('topic_id', $topic->id)->ordered()->get();

        return $lessons->first(fn ($l) => ! $completed->has($l->id)) ?? $lessons->first();
    }

    private function untakenExamFor(User $student, Topic $topic): ?Exam
    {
        $taken = ExamAttempt::where('user_id', $student->id)->pluck('exam_id');

        return Exam::query()
            ->published()
            ->open()
            ->whereNotIn('id', $taken)
            ->whereHas('questions', fn ($q) => $q->where('topic_id', $topic->id))
            ->first();
    }

    private function firstLessonForGrade(User $student, Collection $completed): ?Lesson
    {
        $gradeId = $student->loadMissing('studentProfile')->studentProfile?->grade_id;

        if (! $gradeId) {
            return null;
        }

        return Lesson::query()
            ->published()
            ->whereHas('topic.chapter.subject', fn ($q) => $q->where('grade_id', $gradeId))
            ->whereNotIn('id', $completed->keys())
            ->with('topic')
            ->ordered()
            ->first();
    }

    /** @return array<string, mixed> */
    private function make(User $student, string $type, Topic $topic, string $targetType, int $targetId, ?string $difficulty, int $priority, string $reason): array
    {
        return [
            'user_id' => $student->id,
            'type' => $type,
            'topic_id' => $topic->id,
            'target_type' => $targetType,
            'target_id' => $targetId,
            'difficulty' => $difficulty,
            'reason' => mb_substr($reason, 0, 255),
            'priority' => max(0, $priority),
            'status' => 'new',
            'expires_at' => now()->addDays(self::TTL_DAYS),
        ];
    }
}
