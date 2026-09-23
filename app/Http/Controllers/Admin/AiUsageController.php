<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AiGenerationDraft;
use App\Models\AiUsage;
use App\Models\AuditLog;
use App\Services\AI\AiUsageGuard;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Theo dõi chi phí AI. Số tiền là ƯỚC TÍNH theo bảng giá trong config/ai.php —
 * đối chiếu với trang billing của nhà cung cấp.
 */
class AiUsageController extends Controller
{
    public function index(AiProviderInterface $provider): View
    {
        $since30 = today()->subDays(29);

        $totals = fn ($from) => AiUsage::query()
            ->whereDate('usage_date', '>=', $from)
            ->selectRaw('COALESCE(SUM(request_count),0) requests, COALESCE(SUM(failed_count),0) failed,
                         COALESCE(SUM(tokens_in),0) tokens_in, COALESCE(SUM(tokens_out),0) tokens_out,
                         COALESCE(SUM(cost_estimate),0) cost')
            ->first();

        $daily = $this->dailyForChart();

        return view('admin.ai-usage.index', [
            'providerName' => $provider->name(),
            'isFake' => config('ai.provider') !== 'openai',
            'pricingMissing' => config('ai.provider') === 'openai' && ! AiUsageGuard::hasPricing((string) config('ai.openai.model')),
            'model' => (string) config('ai.openai.model'),
            'keyConfigured' => filled(config('ai.openai.api_key')),
            'today' => $totals(today()),
            'week' => $totals(today()->subDays(6)),
            'month' => $totals($since30),
            'daily' => $daily,
            'byFeature' => AiUsage::query()
                ->whereDate('usage_date', '>=', $since30)
                ->groupBy('feature')
                ->selectRaw('feature, SUM(request_count) requests, SUM(failed_count) failed, SUM(cost_estimate) cost')
                ->orderByDesc('requests')
                ->get(),
            'topUsers' => AiUsage::query()
                ->whereDate('usage_date', '>=', $since30)
                ->join('users', 'users.id', '=', 'ai_usage.user_id')
                ->groupBy('users.id', 'users.name', 'users.email')
                ->select('users.id', 'users.name', 'users.email',
                    DB::raw('SUM(ai_usage.request_count) requests'), DB::raw('SUM(ai_usage.cost_estimate) cost'))
                ->orderByDesc('requests')
                ->limit(10)
                ->get(),
            'stuckDrafts' => AiGenerationDraft::query()
                ->whereIn('status', [AiGenerationDraft::STATUS_PENDING, AiGenerationDraft::STATUS_PROCESSING])
                ->where('created_at', '<', now()->subMinutes(5))
                ->count(),
            // ScopeGuard (§10): tin nhắn ngoài lề mà AI không từ chối đúng cách — cần người xem lại hội thoại.
            'offTopicSuspected' => AuditLog::query()
                ->where('action', 'ai.off_topic_suspected')
                ->where('created_at', '>=', $since30)
                ->count(),
        ]);
    }

    /**
     * Chuỗi 14 ngày cho biểu đồ. Ngày không có lượt gọi vẫn có mặt (giá trị 0) để trục thời gian liền mạch.
     *
     * @return list<array{label: string, requests: int, cost: float}>
     */
    private function dailyForChart(): array
    {
        $since = today()->subDays(13);

        $rows = AiUsage::query()
            ->whereDate('usage_date', '>=', $since)
            ->groupBy('usage_date')
            ->selectRaw('usage_date, SUM(request_count) requests, SUM(cost_estimate) cost')
            ->get()
            ->keyBy(fn ($r) => Carbon::parse($r->usage_date)->toDateString());

        return collect(range(0, 13))->map(function (int $i) use ($since, $rows) {
            $day = $since->copy()->addDays($i)->toDateString();
            $row = $rows->get($day);

            return [
                'label' => Carbon::parse($day)->format('d/m'),
                'requests' => (int) ($row->requests ?? 0),
                'cost' => round((float) ($row->cost ?? 0), 4),
            ];
        })->all();
    }
}
