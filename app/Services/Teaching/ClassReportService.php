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
 * Báo cáo theo lớp cho giáo viên (§13): tiến độ bài giao, điểm theo bài, chủ đề cả lớp còn yếu,
 * bảng học sinh (dùng lại ngưỡng của StudentInsightService để hai màn hình nói cùng một điều).
 */
class ClassReportService
{
    public function __construct(private readonly StudentInsightService $insights) {}

    /** @return array<string, mixed> */
    public function forClass(User $teacher, SchoolClass $class): array
    {
        $students = $this->insights->studentsFor($teacher, $class->id);
        $studentIds = $students->pluck('student.id');

        $assignments = Assignment::query()
            ->where('class_id', $class->id)
            ->where('status', Assignment::STATUS_PUBLISHED)
            ->withCount([
                'recipients',
                'recipients as done_count' => fn ($q) => $q->where('status', '!=', AssignmentStudent::STATUS_ASSIGNED),
            ])
            ->withAvg('recipients as avg_percent', 'percent')
            ->latest('published_at')
            ->limit(20)
            ->get();

        $recipients = $assignments->sum('recipients_count');
        $done = $assignments->sum('done_count');
        $scored = $students->pluck('avg_percent')->filter(fn ($v) => $v !== null);

        return [
            'summary' => [
                'students' => $students->count(),
                'assignments' => $assignments->count(),
                'completion' => $recipients > 0 ? (int) round($done / $recipients * 100) : null,
                'average' => $scored->isNotEmpty() ? (int) round($scored->avg()) : null,
                'needs_support' => $this->insights->filter($students, 'needs_support')->count(),
            ],
            'assignments' => $assignments,
            'topics' => $this->topicMastery($studentIds),
            'students' => $students->sortBy(fn ($row) => $row['avg_percent'] ?? -1)->values(), // chưa có điểm lên đầu: cần nhắc làm bài
        ];
    }

    /**
     * Mức thành thạo trung bình của cả lớp theo chủ đề — chỉ tính học sinh đã làm đủ số câu để tin được.
     *
     * @param  Collection<int, int>  $studentIds
     * @return list<array{topic: string, percent: int, students: int, weak: int}>
     */
    private function topicMastery(Collection $studentIds): array
    {
        if ($studentIds->isEmpty()) {
            return [];
        }

        return StudentTopicMastery::query()
            ->join('topics', 'topics.id', '=', 'student_topic_mastery.topic_id')
            ->whereIn('student_topic_mastery.user_id', $studentIds)
            ->whereRaw('(correct_count + wrong_count) >= ?', [MasteryService::MIN_ATTEMPTS_FOR_CONFIDENCE])
            ->groupBy('topics.id', 'topics.name')
            ->orderByRaw('AVG(mastery_score)')
            ->limit(12)
            ->get([
                'topics.name',
                DB::raw('ROUND(AVG(mastery_score)) as percent'),
                DB::raw('COUNT(*) as students'),
                DB::raw('SUM(CASE WHEN mastery_score < '.(int) StudentTopicMastery::WEAK_THRESHOLD.' THEN 1 ELSE 0 END) as weak'),
            ])
            ->map(fn ($r) => [
                'topic' => $r->name,
                'percent' => (int) $r->percent,
                'students' => (int) $r->students,
                'weak' => (int) $r->weak,
            ])
            ->all();
    }
}
