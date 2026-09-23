<?php

namespace App\Services\Learning;

use App\Models\AssignmentStudent;
use App\Models\AssignmentSubmission;
use App\Models\ExamAttempt;
use App\Models\LearningPath;
use App\Models\Lesson;
use App\Models\QuestionAttempt;
use App\Models\StudentLessonProgress;
use App\Models\TeacherComment;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Báo cáo học tập của một học sinh cho phụ huynh (§14).
 *
 * Mỗi con số ở đây phải giải thích được cho phụ huynh — định nghĩa ghi ngay tại hàm tính.
 */
class StudentReportService
{
    public function __construct(
        private readonly MasteryService $mastery,
        private readonly RecommendationService $recommendations,
    ) {}

    /** @return array<string, mixed> */
    public function summary(User $student): array
    {
        $student->loadMissing('studentProfile.grade');

        return [
            'grade' => $student->studentProfile?->grade,
            'curriculum_percent' => $this->curriculumPercent($student),
            'lessons_completed' => StudentLessonProgress::where('user_id', $student->id)
                ->where('status', StudentLessonProgress::STATUS_COMPLETED)->count(),
            'average_score' => $this->averageScoreOutOf10($student),
            'study_seconds' => $this->studySeconds($student),
            'strong_topics' => $this->mastery->strongTopics($student, 3),
            'weak_topics' => $this->mastery->weakTopics($student, 3),
            'recommendations' => $this->recommendations->current($student, 4),
            // §36: lộ trình cá nhân hóa (nếu con đã làm kiểm tra đầu vào).
            'path' => LearningPath::where('user_id', $student->id)
                ->whereIn('status', ['active', 'completed'])->with('stages', 'placementTest')->latest('id')->first(),
            'comments' => TeacherComment::query()
                ->where('student_id', $student->id)
                ->where('visible_to_parent', true)
                ->with('teacher')
                ->latest()
                ->limit(5)
                ->get(),
            'pending_assignments' => $this->pendingAssignments($student),
            'recent_exams' => ExamAttempt::query()
                ->where('user_id', $student->id)
                ->where('status', '!=', ExamAttempt::STATUS_IN_PROGRESS)
                ->with('exam')
                ->latest('submitted_at')
                ->limit(5)
                ->get(),
            'activity' => $this->dailyActivity($student, 7),
        ];
    }

    /**
     * Tiến độ chương trình = số bài học đã hoàn thành / tổng bài học đã xuất bản của lớp con đang học.
     * Chưa chọn lớp hoặc lớp chưa có bài → null (không bịa ra 0%).
     */
    public function curriculumPercent(User $student): ?int
    {
        $gradeId = $student->studentProfile?->grade_id;

        if (! $gradeId) {
            return null;
        }

        $lessonIds = Lesson::query()
            ->published()
            ->whereHas('topic.chapter.subject', fn ($q) => $q->where('grade_id', $gradeId))
            ->pluck('id');

        if ($lessonIds->isEmpty()) {
            return null;
        }

        $done = StudentLessonProgress::query()
            ->where('user_id', $student->id)
            ->whereIn('lesson_id', $lessonIds)
            ->where('status', StudentLessonProgress::STATUS_COMPLETED)
            ->count();

        return (int) round($done / $lessonIds->count() * 100);
    }

    /**
     * Điểm trung bình thang 10 = trung bình % của mọi lượt làm đề đã chấm và mọi lần nộp bài tập được giao.
     * Luyện tập tự do không tính — đó là lúc con đang tập, sai là bình thường.
     */
    public function averageScoreOutOf10(User $student, ?Carbon $from = null, ?Carbon $to = null): ?float
    {
        $percents = ExamAttempt::query()
            ->where('user_id', $student->id)
            ->where('status', ExamAttempt::STATUS_GRADED)
            ->where('total_points', '>', 0)
            ->when($from, fn ($q) => $q->whereBetween('submitted_at', [$from, $to]))
            ->get(['score', 'total_points'])
            ->map(fn ($a) => (float) $a->score / (float) $a->total_points * 100);

        $percents = $percents->merge(
            AssignmentSubmission::query()
                ->where('student_id', $student->id)
                ->where('max_score', '>', 0)
                ->when($from, fn ($q) => $q->whereBetween('submitted_at', [$from, $to]))
                ->get(['score', 'max_score'])
                ->map(fn ($s) => (float) $s->score / (float) $s->max_score * 100),
        );

        return $percents->isEmpty() ? null : round($percents->avg() / 10, 1);
    }

    /**
     * Thời gian học = thời gian đọc bài học + thời gian làm từng câu hỏi (luyện tập, bài tập, đề).
     * Chỉ đếm thời gian có tương tác, không đếm lúc tab mở mà bỏ đi.
     */
    public function studySeconds(User $student): int
    {
        return (int) StudentLessonProgress::where('user_id', $student->id)->sum('time_spent_seconds')
            + (int) QuestionAttempt::where('user_id', $student->id)->sum('time_spent_seconds');
    }

    /** @return array{pending: int, overdue: int} */
    public function pendingAssignments(User $student): array
    {
        $rows = AssignmentStudent::query()
            ->where('student_id', $student->id)
            ->where('status', AssignmentStudent::STATUS_ASSIGNED)
            ->whereHas('assignment', fn ($q) => $q->whereNull('deleted_at')->where('status', 'published'))
            ->with('assignment:id,due_at')
            ->get();

        return [
            'pending' => $rows->count(),
            'overdue' => $rows->filter(fn ($r) => $r->assignment->isOverdue())->count(),
        ];
    }

    /**
     * Số câu đã làm mỗi ngày, `days` ngày gần nhất (tính cả hôm nay). Ngày không học vẫn có mặt với 0.
     *
     * @return array<int, array{date: string, label: string, answered: int, correct: int}>
     */
    public function dailyActivity(User $student, int $days): array
    {
        $start = now()->startOfDay()->subDays($days - 1);

        $rows = QuestionAttempt::query()
            ->where('user_id', $student->id)
            ->where('created_at', '>=', $start)
            ->groupBy(DB::raw('DATE(created_at)'))
            ->selectRaw('DATE(created_at) as d, COUNT(*) as answered, SUM(is_correct = 1) as correct')
            ->get()
            ->keyBy('d');

        $weekdays = ['CN', 'T2', 'T3', 'T4', 'T5', 'T6', 'T7'];

        return collect(range(0, $days - 1))
            ->map(function (int $i) use ($start, $rows, $weekdays) {
                $day = $start->copy()->addDays($i);
                $row = $rows->get($day->toDateString());

                return [
                    'date' => $day->toDateString(),
                    'label' => $weekdays[$day->dayOfWeek].' '.$day->format('d/m'),
                    'answered' => (int) ($row->answered ?? 0),
                    'correct' => (int) ($row->correct ?? 0),
                ];
            })
            ->all();
    }

    /**
     * Số liệu một tuần cho email báo cáo.
     *
     * @return array<string, mixed>
     */
    public function weekly(User $student, Carbon $from, Carbon $to): array
    {
        $attempts = QuestionAttempt::query()
            ->where('user_id', $student->id)
            ->whereBetween('created_at', [$from, $to])
            ->whereNotNull('is_correct')
            ->get(['is_correct']);

        return [
            'student' => $student,
            'lessons_completed' => StudentLessonProgress::query()
                ->where('user_id', $student->id)
                ->whereBetween('completed_at', [$from, $to])
                ->count(),
            'questions_answered' => $attempts->count(),
            'accuracy' => $attempts->isEmpty() ? null : (int) round($attempts->where('is_correct', true)->count() / $attempts->count() * 100),
            'exams_finished' => ExamAttempt::query()
                ->where('user_id', $student->id)
                ->where('status', '!=', ExamAttempt::STATUS_IN_PROGRESS)
                ->whereBetween('submitted_at', [$from, $to])
                ->count(),
            'average_score' => $this->averageScoreOutOf10($student, $from, $to),
            'assignments' => $this->pendingAssignments($student),
            'comments' => TeacherComment::query()
                ->where('student_id', $student->id)
                ->where('visible_to_parent', true)
                ->whereBetween('created_at', [$from, $to])
                ->with('teacher')
                ->get(),
            'weak_topics' => $this->mastery->weakTopics($student, 3),
        ];
    }

    /** Tuần có hoạt động gì đáng báo không — tuần trống thì không gửi mail làm phiền. */
    public function hasWeeklyActivity(array $weekly): bool
    {
        return $weekly['lessons_completed'] > 0
            || $weekly['questions_answered'] > 0
            || $weekly['exams_finished'] > 0
            || $weekly['comments']->isNotEmpty()
            || $weekly['assignments']['overdue'] > 0;
    }
}
