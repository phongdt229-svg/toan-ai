<?php

namespace App\Services\Admin;

use App\Models\AiUsage;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Số liệu dashboard admin. Toàn truy vấn tổng hợp trên bảng lớn → cache 10 phút
 * (production dùng Redis); admin cần xu hướng, không cần số đến từng giây.
 */
class AnalyticsService
{
    public const CACHE_KEY = 'admin.analytics.v1';

    public const CACHE_SECONDS = 600;

    /** @return array<string, mixed> */
    public function overview(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => $this->compute() + [
            'generated_at' => now()->toIso8601String(),
        ]);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return array<string, mixed> */
    private function compute(): array
    {
        $monthStart = now()->startOfMonth();
        $since = today()->subDays(29);

        return [
            'users' => [
                'total' => User::count(),
                'students' => $this->countByRole(Role::STUDENT),
                'teachers' => $this->countByRole(Role::TEACHER),
                'parents' => $this->countByRole(Role::PARENT),
                'new_30d' => User::where('created_at', '>=', $since)->count(),
                'pending_teachers' => User::where('status', User::STATUS_PENDING)->count(),
            ],
            'engagement' => [
                // Học sinh "hoạt động" = có làm ít nhất một câu hỏi (luyện tập, đề, đầu vào…).
                'active_7d' => QuestionAttempt::where('created_at', '>=', today()->subDays(6))->distinct()->count('user_id'),
                'active_30d' => QuestionAttempt::where('created_at', '>=', $since)->distinct()->count('user_id'),
                'answers_7d' => QuestionAttempt::where('created_at', '>=', today()->subDays(6))->count(),
            ],
            'revenue' => [
                'month' => (int) Payment::where('status', Payment::STATUS_PAID)->where('paid_at', '>=', $monthStart)->sum(DB::raw('amount - refunded_amount')),
                'last_30d' => (int) Payment::where('status', Payment::STATUS_PAID)->where('paid_at', '>=', $since)->sum(DB::raw('amount - refunded_amount')),
                'paid_orders_month' => Payment::where('status', Payment::STATUS_PAID)->where('paid_at', '>=', $monthStart)->count(),
            ],
            'subscriptions' => $this->effectiveByTier(),
            'ai_cost_month' => round((float) AiUsage::where('usage_date', '>=', $monthStart->toDateString())->sum('cost_estimate'), 2),
            'content' => [
                'lessons' => Lesson::published()->count(),
                'questions' => Question::published()->count(),
                'exams' => Exam::published()->count(),
            ],
            'daily' => $this->daily($since),
            'weak_topics' => $this->weakTopics(),
            'support' => [
                'open' => SupportTicket::query()->open()->count(),
                'content_error' => SupportTicket::where('type', SupportTicket::TYPE_CONTENT_ERROR)->open()->count(),
            ],
        ];
    }

    private function countByRole(string $role): int
    {
        return User::whereHas('roles', fn ($q) => $q->where('name', $role))->count();
    }

    /** @return array<string, int> */
    private function effectiveByTier(): array
    {
        $counts = Subscription::query()
            ->effective()
            ->join('packages', 'packages.id', '=', 'subscriptions.package_id')
            ->groupBy('packages.tier')
            ->select('packages.tier', DB::raw('COUNT(DISTINCT subscriptions.user_id) as students'))
            ->pluck('students', 'tier');

        return collect([Package::TIER_PRO, Package::TIER_PREMIUM])
            ->mapWithKeys(fn ($tier) => [$tier => (int) ($counts[$tier] ?? 0)])
            ->all();
    }

    /**
     * Chuỗi 30 ngày cho biểu đồ. Ngày không có dữ liệu vẫn có mặt (giá trị 0) để trục thời gian liền mạch.
     *
     * @return list<array{label: string, signups: int, active: int, revenue: int}>
     */
    private function daily(Carbon $since): array
    {
        $signups = User::where('created_at', '>=', $since)
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) d, COUNT(*) c')
            ->pluck('c', 'd');

        $active = QuestionAttempt::where('created_at', '>=', $since)
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) d, COUNT(DISTINCT user_id) c')
            ->pluck('c', 'd');

        $revenue = Payment::where('status', Payment::STATUS_PAID)
            ->where('paid_at', '>=', $since)
            ->groupByRaw('DATE(paid_at)')
            ->selectRaw('DATE(paid_at) d, SUM(amount - refunded_amount) c')
            ->pluck('c', 'd');

        return collect(range(0, 29))->map(function (int $i) use ($since, $signups, $active, $revenue) {
            $day = $since->copy()->addDays($i)->toDateString();

            return [
                'label' => Carbon::parse($day)->format('d/m'),
                'signups' => (int) ($signups[$day] ?? 0),
                'active' => (int) ($active[$day] ?? 0),
                'revenue' => (int) ($revenue[$day] ?? 0),
            ];
        })->all();
    }

    /**
     * Chủ đề nhiều học sinh yếu nhất — gợi ý cho đội nội dung soạn thêm bài/câu hỏi.
     * Chỉ tính chủ đề có ít nhất 3 học sinh để tránh nhiễu.
     *
     * @return list<array{topic: string, students: int, avg: int}>
     */
    private function weakTopics(): array
    {
        return DB::table('student_topic_mastery')
            ->join('topics', 'topics.id', '=', 'student_topic_mastery.topic_id')
            ->groupBy('topics.id', 'topics.name')
            ->havingRaw('COUNT(*) >= 3')
            ->orderByRaw('AVG(student_topic_mastery.mastery_score)')
            ->limit(5)
            ->get(['topics.name', DB::raw('COUNT(*) as students'), DB::raw('AVG(student_topic_mastery.mastery_score) as avg_score')])
            ->map(fn ($r) => ['topic' => $r->name, 'students' => (int) $r->students, 'avg' => (int) round($r->avg_score)])
            ->all();
    }
}
