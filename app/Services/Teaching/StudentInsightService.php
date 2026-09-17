<?php

namespace App\Services\Teaching;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\SchoolClass;
use App\Models\StudentTopicMastery;
use App\Models\User;
use App\Services\Learning\MasteryService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu học sinh cho portal giáo viên (§13).
 *
 * Bộ lọc: Tất cả / Cần hỗ trợ / Chưa làm bài / Điểm thấp / Đang tiến bộ.
 * Mọi ngưỡng đặt thành hằng số ở đây để giáo viên hỏi "vì sao em này bị đánh dấu" thì trả lời được.
 */
class StudentInsightService
{
    public const FILTERS = [
        'all' => 'Tất cả',
        'needs_support' => 'Cần hỗ trợ',
        'not_done' => 'Chưa làm bài',
        'low_score' => 'Điểm thấp',
        'improving' => 'Đang tiến bộ',
    ];

    /** Điểm trung bình dưới mức này (%) → điểm thấp. */
    public const LOW_SCORE_PERCENT = 50;

    /** Số bài quá hạn chưa nộp từ mức này → cần hỗ trợ. */
    public const OVERDUE_FOR_SUPPORT = 2;

    /** Số chủ đề yếu từ mức này → cần hỗ trợ. */
    public const WEAK_TOPICS_FOR_SUPPORT = 2;

    /** Điểm TB 3 bài gần nhất cao hơn các bài trước ít nhất chừng này (điểm %) → tiến bộ. */
    public const IMPROVING_DELTA = 10;

    /**
     * @return Collection<int, array{
     *     student: User, classes: array<int, string>, assigned: int, done: int, overdue: int,
     *     avg_percent: ?int, trend: ?int, weak_topics: int, flags: array<int, string>
     * }>
     */
    public function studentsFor(User $teacher, ?int $classId = null): Collection
    {
        $classes = SchoolClass::query()
            ->taughtBy($teacher)
            ->when($classId, fn ($q) => $q->whereKey($classId))
            ->with(['activeStudents' => fn ($q) => $q->orderBy('name')])
            ->get();

        $students = $classes->flatMap->activeStudents->unique('id')->values();

        if ($students->isEmpty()) {
            return collect();
        }

        $studentIds = $students->pluck('id');

        // Chỉ tính bài giao còn hiệu lực trong các lớp của giáo viên này. Relation `assignment`
        // có withTrashed (để học sinh xem lại điểm) nên phải loại bài đã xoá ở đây.
        $records = AssignmentStudent::query()
            ->whereIn('student_id', $studentIds)
            ->whereHas('assignment', fn ($q) => $q->whereIn('class_id', $classes->pluck('id'))->whereNull('deleted_at'))
            ->with('assignment:id,due_at,status,class_id')
            ->get()
            ->groupBy('student_id');

        $weakCounts = StudentTopicMastery::query()
            ->whereIn('user_id', $studentIds)
            ->where('mastery_score', '<', StudentTopicMastery::WEAK_THRESHOLD)
            ->whereRaw('(correct_count + wrong_count) >= ?', [MasteryService::MIN_ATTEMPTS_FOR_CONFIDENCE])
            ->groupBy('user_id')
            ->select('user_id', DB::raw('COUNT(*) as n'))
            ->pluck('n', 'user_id');

        $classNames = $classes->flatMap(fn ($c) => $c->activeStudents->map(fn ($s) => [$s->id, $c->name]))
            ->groupBy(0)
            ->map(fn ($rows) => $rows->pluck(1)->all());

        return $students->map(function (User $student) use ($records, $weakCounts, $classNames) {
            $rows = $records->get($student->id, collect());

            $metrics = [
                'student' => $student,
                'classes' => $classNames->get($student->id, []),
                'assigned' => $rows->count(),
                'done' => $rows->filter->isDone()->count(),
                'overdue' => $rows->filter(fn (AssignmentStudent $r) => ! $r->isDone() && $r->assignment?->isOverdue())->count(),
                'avg_percent' => $this->averagePercent($rows),
                'trend' => $this->trend($rows),
                'weak_topics' => (int) ($weakCounts[$student->id] ?? 0),
            ];

            $metrics['flags'] = $this->flags($metrics);

            return $metrics;
        });
    }

    /** @param  Collection<int, array<string, mixed>>  $insights */
    public function filter(Collection $insights, string $filter): Collection
    {
        if ($filter === 'all' || ! array_key_exists($filter, self::FILTERS)) {
            return $insights;
        }

        return $insights->filter(fn ($row) => in_array($filter, $row['flags'], true))->values();
    }

    /** @return array<string, int|null> */
    public function dashboardStats(User $teacher): array
    {
        $classIds = SchoolClass::query()->taughtBy($teacher)->active()->pluck('id');
        $insights = $this->studentsFor($teacher);

        $avg = AssignmentStudent::query()
            ->whereNotNull('percent')
            ->whereHas('assignment', fn ($q) => $q->whereIn('class_id', $classIds)->whereNull('deleted_at'))
            ->avg('percent');

        return [
            'classes' => $classIds->count(),
            'students' => $insights->count(),
            'open_assignments' => Assignment::query()
                ->whereIn('class_id', $classIds)
                ->where('status', Assignment::STATUS_PUBLISHED)
                ->where(fn ($q) => $q->whereNull('due_at')->orWhere('due_at', '>', now()))
                ->count(),
            'average_score' => $avg !== null ? (int) round($avg) : null,
            'students_needing_help' => $this->filter($insights, 'needs_support')->count(),
        ];
    }

    /** @param  array<string, mixed>  $m */
    private function flags(array $m): array
    {
        $flags = [];

        $lowScore = $m['avg_percent'] !== null && $m['avg_percent'] < self::LOW_SCORE_PERCENT;

        if ($lowScore) {
            $flags[] = 'low_score';
        }

        if ($m['overdue'] > 0) {
            $flags[] = 'not_done';
        }

        if ($lowScore
            || $m['overdue'] >= self::OVERDUE_FOR_SUPPORT
            || $m['weak_topics'] >= self::WEAK_TOPICS_FOR_SUPPORT) {
            $flags[] = 'needs_support';
        }

        if ($m['trend'] !== null && $m['trend'] >= self::IMPROVING_DELTA) {
            $flags[] = 'improving';
        }

        return $flags;
    }

    /** @param  Collection<int, AssignmentStudent>  $rows */
    private function averagePercent(Collection $rows): ?int
    {
        $scored = $rows->whereNotNull('percent');

        return $scored->isEmpty() ? null : (int) round($scored->avg('percent'));
    }

    /**
     * Chênh lệch điểm TB giữa 3 bài gần nhất và các bài trước đó.
     * Cần ít nhất 4 bài có điểm, ít hơn thì chưa đủ dữ liệu để nói "tiến bộ".
     *
     * @param  Collection<int, AssignmentStudent>  $rows
     */
    private function trend(Collection $rows): ?int
    {
        $scored = $rows->whereNotNull('percent')->whereNotNull('completed_at')->sortBy('completed_at')->values();

        if ($scored->count() < 4) {
            return null;
        }

        $recent = $scored->slice(-3);
        $before = $scored->slice(0, $scored->count() - 3);

        return (int) round($recent->avg('percent') - $before->avg('percent'));
    }
}
