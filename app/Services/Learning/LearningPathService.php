<?php

namespace App\Services\Learning;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\LearningPath;
use App\Models\LearningPathItem;
use App\Models\LearningPathStage;
use App\Models\Lesson;
use App\Models\PlacementTest;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\StudentLessonProgress;
use App\Models\StudySession;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Giáo trình cá nhân hóa (§35): lộ trình 4 giai đoạn Nền tảng → Củng cố → Nâng cao → Luyện đề,
 * chia thành buổi học (§36), mỗi buổi kết thúc bằng kiểm tra ngắn (§37).
 *
 * Lộ trình TỰ ĐIỀU CHỈNH: mục tự đánh dấu xong khi học sinh học/luyện/làm đề thật,
 * và chèn mục ôn tập khi một chủ đề đã học tụt xuống dưới ngưỡng yếu.
 */
class LearningPathService
{
    public const ITEMS_PER_SESSION = 3;

    /** Luyện ít nhất chừng này câu của chủ đề (đúng độ khó) thì mục luyện tập tính là xong. */
    public const PRACTICE_ATTEMPTS_TO_COMPLETE = 5;

    public const QUIZ_QUESTIONS = 5;

    public const QUIZ_PASS_PERCENT = 50;

    public function __construct(
        private readonly MasteryService $mastery,
        private readonly GradingService $grading,
    ) {}

    public function active(User $student): ?LearningPath
    {
        return LearningPath::query()
            ->where('user_id', $student->id)
            ->whereIn('status', [LearningPath::STATUS_ACTIVE, LearningPath::STATUS_COMPLETED])
            ->latest('id')
            ->first();
    }

    // --- Sinh lộ trình ---------------------------------------------------------------------

    public function generate(User $student, ?PlacementTest $test = null): LearningPath
    {
        $gradeId = $test?->grade_id ?? $student->loadMissing('studentProfile')->studentProfile?->grade_id;

        if (! $gradeId) {
            throw new PlacementException('Em cần chọn lớp trong hồ sơ trước.');
        }

        $level = $test?->level_result ?? $student->studentProfile?->self_assessed_level ?? 'good';

        return DB::transaction(function () use ($student, $test, $gradeId, $level) {
            // Làm lại kiểm tra đầu vào → lộ trình cũ lưu trữ, không xoá (còn lịch sử).
            LearningPath::where('user_id', $student->id)
                ->whereIn('status', [LearningPath::STATUS_ACTIVE, LearningPath::STATUS_COMPLETED])
                ->update(['status' => LearningPath::STATUS_ARCHIVED]);

            $path = LearningPath::create([
                'user_id' => $student->id,
                'grade_id' => $gradeId,
                'placement_test_id' => $test?->id,
                'status' => LearningPath::STATUS_ACTIVE,
                'items_per_session' => self::ITEMS_PER_SESSION,
                'generated_at' => now(),
            ]);

            $plan = $this->buildPlan($student, $gradeId, $level, $test);
            $completedLessons = StudentLessonProgress::where('user_id', $student->id)
                ->where('status', StudentLessonProgress::STATUS_COMPLETED)
                ->pluck('lesson_id')
                ->flip();

            $pending = collect();
            $order = 0;

            foreach (array_keys(LearningPathStage::STAGES) as $i => $key) {
                $stage = $path->stages()->create([
                    'stage' => $key,
                    'name' => LearningPathStage::STAGES[$key],
                    'sort_order' => $i + 1,
                ]);

                foreach ($plan[$key] as $spec) {
                    $alreadyDone = $spec['item_type'] === LearningPathItem::TYPE_LESSON && $completedLessons->has($spec['target_id']);

                    $item = $stage->items()->create([
                        ...$spec,
                        'sort_order' => ++$order,
                        // Bài đã học xong từ trước thì không bắt học lại.
                        'status' => $alreadyDone ? 'done' : 'pending',
                        'completed_at' => $alreadyDone ? now() : null,
                    ]);

                    if (! $alreadyDone) {
                        $pending->push($item);
                    }
                }
            }

            foreach ($pending->chunk(self::ITEMS_PER_SESSION)->values() as $i => $chunk) {
                $session = StudySession::create([
                    'user_id' => $student->id,
                    'learning_path_id' => $path->id,
                    'session_no' => $i + 1,
                ]);

                LearningPathItem::whereIn('id', $chunk->pluck('id'))->update(['study_session_id' => $session->id]);
            }

            $this->recalculate($path);

            return $path->refresh();
        });
    }

    /**
     * @return array<string, array<int, array<string, mixed>>> stage => danh sách mục
     */
    private function buildPlan(User $student, int $gradeId, string $level, ?PlacementTest $test): array
    {
        $topics = Topic::query()
            ->whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $gradeId))
            ->with('chapter')
            ->get()
            // Thứ tự chương trình: chương trước → chủ đề trước. Kiến thức nền nằm ở chủ đề đứng trước.
            ->sortBy([fn ($a, $b) => $a->chapter->sort_order <=> $b->chapter->sort_order,
                fn ($a, $b) => $a->sort_order <=> $b->sort_order,
                fn ($a, $b) => $a->id <=> $b->id])
            ->values();

        $topicIds = $topics->pluck('id');

        $lessons = Lesson::published()->whereIn('topic_id', $topicIds)->ordered()->get()->groupBy('topic_id');

        // Số câu tự chấm theo chủ đề × độ khó — chỉ tạo mục luyện tập khi thực sự có câu để luyện.
        $questionCounts = Question::published()
            ->whereIn('topic_id', $topicIds)
            ->where('type', '!=', Question::TYPE_ESSAY)
            ->groupBy('topic_id', 'difficulty')
            ->select('topic_id', 'difficulty', DB::raw('COUNT(*) as n'))
            ->get()
            ->groupBy('topic_id')
            ->map(fn ($rows) => $rows->pluck('n', 'difficulty'));

        $weakIds = collect($test?->weak_topics ?? [])->pluck('topic_id')
            ->merge($this->mastery->weakTopics($student, 20)->pluck('topic_id'))
            ->unique();

        // Giai đoạn 1 — Nền tảng: chủ đề yếu, kèm chủ đề đứng ngay trước nó (kiến thức nền).
        $foundation = collect();
        foreach ($topics as $index => $topic) {
            if (! $weakIds->contains($topic->id)) {
                continue;
            }

            $previous = $topics->get($index - 1);
            if ($previous && $previous->chapter_id === $topic->chapter_id) {
                $foundation->push($previous);
            }
            $foundation->push($topic);
        }

        // Không phát hiện điểm yếu → vẫn ôn nhanh 2 chủ đề đầu cho chắc gốc.
        if ($foundation->isEmpty()) {
            $foundation = $topics->take(2);
        }

        $foundation = $foundation->unique('id')->values();
        $rest = $topics->whereNotIn('id', $foundation->pluck('id'))->values();

        $practice = function (Topic $topic, ?string $difficulty) use ($questionCounts): ?array {
            $counts = $questionCounts->get($topic->id, collect());

            if ($counts->isEmpty()) {
                return null;
            }

            // Không đủ câu đúng độ khó → luyện hỗn hợp thay vì tạo mục không làm được.
            $use = $difficulty && ($counts[$difficulty] ?? 0) > 0 ? $difficulty : null;
            $label = ['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'][$use] ?? 'Hỗn hợp';

            return [
                'item_type' => LearningPathItem::TYPE_PRACTICE, 'topic_id' => $topic->id, 'target_id' => null,
                'difficulty' => $use, 'title' => "Luyện tập «{$topic->name}» — {$label}",
            ];
        };

        $lessonItems = fn (Topic $topic) => $lessons->get($topic->id, collect())->map(fn (Lesson $l) => [
            'item_type' => LearningPathItem::TYPE_LESSON, 'topic_id' => $topic->id, 'target_id' => $l->id,
            'difficulty' => null, 'title' => "Học bài «{$l->title}»",
        ])->all();

        $plan = ['foundation' => [], 'consolidation' => [], 'advanced' => [], 'exam_practice' => []];

        // Học sinh giỏi không cần bắt đầu từ câu Dễ.
        $foundationDifficulty = $level === 'excellent' ? 'medium' : 'easy';
        foreach ($foundation as $topic) {
            array_push($plan['foundation'], ...$lessonItems($topic));
            $plan['foundation'][] = $practice($topic, $foundationDifficulty);
        }

        // Giai đoạn 2 — Củng cố: các chủ đề còn lại.
        foreach ($rest as $topic) {
            array_push($plan['consolidation'], ...$lessonItems($topic));
            $plan['consolidation'][] = $practice($topic, 'medium');
        }

        // Giai đoạn 3 — Nâng cao: câu Khó ở mọi chủ đề có câu Khó.
        foreach ($topics as $topic) {
            if (($questionCounts->get($topic->id)['hard'] ?? 0) > 0) {
                $plan['advanced'][] = $practice($topic, 'hard');
            }
        }

        // Giai đoạn 4 — Luyện đề: đề đã xuất bản của lớp; chưa có đề thì luyện hỗn hợp.
        $exams = Exam::published()->open()->where('grade_id', $gradeId)->latest()->limit(3)->get();
        foreach ($exams as $exam) {
            $plan['exam_practice'][] = [
                'item_type' => LearningPathItem::TYPE_EXAM, 'topic_id' => null, 'target_id' => $exam->id,
                'difficulty' => null, 'title' => "Làm đề «{$exam->title}»",
            ];
        }
        if ($exams->isEmpty()) {
            foreach ($topics->filter(fn ($t) => $questionCounts->has($t->id))->take(3) as $topic) {
                $plan['exam_practice'][] = $practice($topic, null);
            }
        }

        return array_map(fn ($items) => array_values(array_filter($items)), $plan);
    }

    // --- Buổi học ------------------------------------------------------------------------

    /** Buổi học hiện tại = buổi đầu tiên chưa xong (§36 "Gợi ý học hôm nay"). */
    public function currentSession(LearningPath $path): ?StudySession
    {
        return $path->sessions()
            ->where('status', '!=', StudySession::STATUS_DONE)
            ->with('items.topic')
            ->first();
    }

    /** Link / form để mở một mục lộ trình. */
    public function targetUrl(LearningPathItem $item): ?string
    {
        return match ($item->item_type) {
            LearningPathItem::TYPE_LESSON => ($slug = Lesson::whereKey($item->target_id)->value('slug')) ? route('student.lesson.show', $slug) : null,
            LearningPathItem::TYPE_EXAM => ($slug = Exam::whereKey($item->target_id)->value('slug')) ? route('student.exams.show', $slug) : null,
            default => null, // luyện tập mở bằng form POST
        };
    }

    // --- Tự điều chỉnh (qua event) ---------------------------------------------------------

    public function syncLessonCompleted(User $student, Lesson $lesson): void
    {
        $this->completeItems($student, fn ($q) => $q->where('item_type', LearningPathItem::TYPE_LESSON)->where('target_id', $lesson->id));
    }

    public function syncExamFinished(ExamAttempt $attempt): void
    {
        $this->completeItems($attempt->user()->first(), fn ($q) => $q->where('item_type', LearningPathItem::TYPE_EXAM)->where('target_id', $attempt->exam_id));
    }

    /**
     * Sau mỗi lần mastery thay đổi: đánh dấu mục luyện tập đã đủ số câu,
     * và chèn ôn tập cho chủ đề ĐÃ học xong mà nay tụt xuống yếu.
     */
    public function syncMastery(User $student): void
    {
        $path = $this->activePath($student);

        if (! $path) {
            return;
        }

        $items = $path->items()->with('stage')->get();

        foreach ($items->where('item_type', LearningPathItem::TYPE_PRACTICE)->where('status', 'pending') as $item) {
            $count = QuestionAttempt::query()
                ->where('user_id', $student->id)
                ->where('topic_id', $item->topic_id)
                ->whereIn('context', [QuestionAttempt::CONTEXT_PRACTICE, QuestionAttempt::CONTEXT_ASSIGNMENT])
                ->when($item->difficulty, fn ($q) => $q->where('difficulty', $item->difficulty))
                // Chỉ tính câu làm SAU khi mục được tạo — luyện từ trước không thay cho bài được giao trong lộ trình.
                ->where('created_at', '>=', $item->created_at)
                ->count();

            if ($count >= self::PRACTICE_ATTEMPTS_TO_COMPLETE) {
                $item->update(['status' => 'done', 'completed_at' => now()]);
            }
        }

        $weak = $this->mastery->weakTopics($student, 50)->pluck('topic_id')->flip();

        $items->groupBy('topic_id')
            ->filter(function ($topicItems, $topicId) use ($weak) {
                $planned = $topicItems->where('origin', 'plan');

                // Chỉ ôn lại chủ đề em ĐÃ học xong theo lộ trình — chủ đề chưa tới lượt thì chưa cần.
                return $topicId && $weak->has($topicId) && $planned->isNotEmpty() && $planned->every->isDone();
            })
            ->each(fn ($topicItems, $topicId) => $this->insertReview($path, (int) $topicId));

        $this->recalculate($path);
    }

    // --- Kiểm tra cuối buổi (§37 — bản mặc định do spec bị cắt) --------------------------------

    /**
     * Bắt đầu (hoặc lấy lại) bộ câu hỏi cuối buổi. Buổi không có chủ đề nào có câu hỏi → tính xong luôn.
     *
     * @return Collection<int, Question>
     */
    public function quizQuestions(StudySession $session): Collection
    {
        if ($session->status !== StudySession::STATUS_QUIZ_PENDING) {
            throw new PlacementException('Buổi học này chưa tới phần kiểm tra cuối buổi.');
        }

        if (! $session->quiz_question_ids) {
            $topicIds = $session->items()->whereNotNull('topic_id')->pluck('topic_id')->unique();

            $ids = Question::published()
                ->whereIn('topic_id', $topicIds)
                ->where('type', '!=', Question::TYPE_ESSAY)
                ->inRandomOrder()
                ->limit(self::QUIZ_QUESTIONS)
                ->pluck('id')
                ->all();

            if ($ids === []) {
                $session->update(['status' => StudySession::STATUS_DONE, 'completed_at' => now()]);
                $this->recalculate($session->loadMissing('path')->path);

                return collect();
            }

            $session->update(['quiz_question_ids' => $ids]);
        }

        $order = array_flip($session->quiz_question_ids);

        return Question::whereIn('id', $session->quiz_question_ids)
            ->with('options')
            ->get()
            ->sortBy(fn ($q) => $order[$q->id])
            ->values();
    }

    /**
     * @param  array<int, mixed>  $answers
     * @return array{percent: int, passed: bool, review_topics: array<int, string>}
     */
    public function submitQuiz(StudySession $session, array $answers): array
    {
        return DB::transaction(function () use ($session, $answers) {
            /** @var StudySession $locked */
            $locked = StudySession::with('path.user')->whereKey($session->id)->lockForUpdate()->firstOrFail();

            if ($locked->status !== StudySession::STATUS_QUIZ_PENDING || ! $locked->quiz_question_ids) {
                throw new PlacementException('Bài kiểm tra cuối buổi này đã nộp hoặc chưa bắt đầu.');
            }

            $student = $locked->path->user;
            $questions = Question::whereIn('id', $locked->quiz_question_ids)->with('options', 'topic')->get();
            $graded = $this->grading->gradeMany($questions, $answers);

            $wrongTopics = collect();
            foreach ($questions as $q) {
                $result = $graded['results'][$q->id];

                QuestionAttempt::create([
                    'user_id' => $student->id,
                    'question_id' => $q->id,
                    'topic_id' => $q->topic_id,
                    'context' => QuestionAttempt::CONTEXT_SESSION_QUIZ,
                    'context_id' => $locked->id,
                    'difficulty' => $q->difficulty,
                    'answer' => ['value' => $answers[$q->id] ?? null],
                    'is_correct' => $result->isCorrect,
                    'score' => $result->score,
                ]);

                if ($result->isCorrect !== true && $q->topic) {
                    $wrongTopics->put($q->topic_id, $q->topic->name);
                }
            }

            $percent = $graded['max_score'] > 0 ? (int) round($graded['score'] / $graded['max_score'] * 100) : 0;
            $passed = $percent >= self::QUIZ_PASS_PERCENT;

            // Chưa đạt vẫn sang buổi mới — nhưng buổi kế tiếp có thêm mục ôn đúng chỗ còn sai.
            if (! $passed) {
                foreach ($wrongTopics->keys() as $topicId) {
                    $this->insertReview($locked->path, $topicId);
                }
            }

            $locked->update([
                'quiz_percent' => $percent,
                'quiz_submitted_at' => now(),
                'status' => StudySession::STATUS_DONE,
                'completed_at' => now(),
            ]);

            $this->mastery->recalculateForTopics($student, $questions->pluck('topic_id')->filter()->all());
            $this->recalculate($locked->path);

            return [
                'percent' => $percent,
                'passed' => $passed,
                'review_topics' => $passed ? [] : $wrongTopics->values()->all(),
            ];
        });
    }

    // --- Nội bộ -----------------------------------------------------------------------------

    public function recalculate(LearningPath $path): void
    {
        $path->load('stages.items', 'sessions.items');

        $allItems = $path->stages->flatMap->items;
        $inProgressAssigned = false;

        foreach ($path->stages as $stage) {
            $total = $stage->items->count();
            $done = $stage->items->filter->isDone()->count();
            // Giai đoạn rỗng chỉ "xong" khi các giai đoạn trước đã xong — tránh hiện "Hoàn thành"
            // ở giai đoạn 2 trong khi giai đoạn 1 còn đang học.
            $complete = $total > 0 ? $done === $total : ! $inProgressAssigned;

            $status = match (true) {
                $complete => 'done',
                $total === 0 => 'locked',
                ! $inProgressAssigned => 'in_progress',
                default => 'locked',
            };

            if ($status === 'in_progress') {
                $inProgressAssigned = true;
            }

            $stage->update([
                'status' => $status,
                'progress_percent' => $total > 0 ? (int) round($done / $total * 100) : 100,
            ]);
        }

        foreach ($path->sessions as $session) {
            if ($session->status === StudySession::STATUS_PLANNED
                && $session->items->isNotEmpty()
                && $session->items->every->isDone()) {
                $session->update(['status' => StudySession::STATUS_QUIZ_PENDING]);
            }
        }

        $totalItems = $allItems->count();
        $doneItems = $allItems->filter->isDone()->count();
        $sessionsDone = $path->sessions->where('status', StudySession::STATUS_DONE)->count();
        $allDone = $totalItems > 0 && $doneItems === $totalItems && $sessionsDone === $path->sessions->count();

        $path->update([
            'total_sessions' => $path->sessions->count(),
            'completed_sessions' => $sessionsDone,
            'progress_percent' => $totalItems > 0 ? (int) round($doneItems / $totalItems * 100) : 0,
            'status' => $allDone ? LearningPath::STATUS_COMPLETED : LearningPath::STATUS_ACTIVE,
        ]);
    }

    private function activePath(User $student): ?LearningPath
    {
        return LearningPath::where('user_id', $student->id)
            ->whereIn('status', [LearningPath::STATUS_ACTIVE, LearningPath::STATUS_COMPLETED])
            ->latest('id')
            ->first();
    }

    private function completeItems(?User $student, \Closure $filter): void
    {
        if (! $student || ! ($path = $this->activePath($student))) {
            return;
        }

        // Lấy id rồi update thẳng bảng items: update qua hasManyThrough sinh JOIN,
        // và cột updated_at/status trùng tên giữa hai bảng làm MariaDB báo mơ hồ.
        $ids = $path->items()
            ->where('learning_path_items.status', 'pending')
            ->where($filter)
            ->pluck('learning_path_items.id');

        $changed = LearningPathItem::whereIn('id', $ids)->update(['status' => 'done', 'completed_at' => now()]);

        if ($changed > 0) {
            $this->recalculate($path);
        }
    }

    /** Chèn một mục ôn tập (luyện Dễ) cho chủ đề vào buổi kế tiếp — không chèn trùng khi đã có mục ôn chưa làm. */
    private function insertReview(LearningPath $path, int $topicId): void
    {
        $exists = $path->items()
            ->where('topic_id', $topicId)
            ->where('origin', 'review')
            ->where('learning_path_items.status', 'pending')
            ->exists();

        $topic = Topic::find($topicId);

        if ($exists || ! $topic) {
            return;
        }

        $stage = $path->stages()->where('status', '!=', 'done')->first() ?? $path->stages()->latest('sort_order')->first();
        $session = $this->nextSession($path);

        $stage->items()->create([
            'study_session_id' => $session->id,
            'item_type' => LearningPathItem::TYPE_PRACTICE,
            'topic_id' => $topicId,
            'difficulty' => 'easy',
            'origin' => 'review',
            'title' => "Ôn lại «{$topic->name}»",
            'sort_order' => (int) $path->items()->max('learning_path_items.sort_order') + 1,
        ]);
    }

    /** Buổi ngay sau buổi hiện tại; hết buổi thì mở buổi mới ở cuối lộ trình. */
    private function nextSession(LearningPath $path): StudySession
    {
        $current = $path->sessions()->where('status', '!=', StudySession::STATUS_DONE)->first();

        $next = $current
            ? $path->sessions()->where('session_no', '>', $current->session_no)->where('status', StudySession::STATUS_PLANNED)->first()
            : null;

        return $next ?? StudySession::create([
            'user_id' => $path->user_id,
            'learning_path_id' => $path->id,
            'session_no' => (int) $path->sessions()->max('session_no') + 1,
        ]);
    }
}
