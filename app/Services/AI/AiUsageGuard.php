<?php

namespace App\Services\AI;

use App\Models\AiUsage;
use App\Models\User;
use App\Services\AccessControlService;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;

/**
 * Giới hạn số lượt AI mỗi ngày và ghi nhận chi phí ước tính.
 * Kiểm tra TRƯỚC khi gọi provider — vượt quota không được tốn tiền.
 */
class AiUsageGuard
{
    public function __construct(
        private readonly AccessControlService $access,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** null = không giới hạn. */
    public function limitFor(User $user): ?int
    {
        $limits = config('ai.daily_limits');

        if ($user->isAdmin()) {
            return null;
        }

        if ($user->isTeacher()) {
            return $limits['teacher'];
        }

        if (! $user->isStudent()) {
            return 0;
        }

        // Giới hạn lấy từ package_features của gói đang dùng (§18); DB chưa có gói thì dùng config.
        $fromPackage = $this->subscriptions->limit($user, 'ai.daily_requests');

        return $fromPackage !== false
            ? $fromPackage
            : ($limits['student'][$this->access->currentTier($user)] ?? $limits['student']['free']);
    }

    /**
     * Lượt AI do hệ thống tự gọi thay học sinh (kiểm tra đầu vào) — vẫn ghi chi phí cho admin,
     * nhưng không trừ vào lượt hỏi AI của học sinh.
     */
    public const SYSTEM_FEATURES = ['placement_generate', 'placement_analysis'];

    public function usedToday(User $user): int
    {
        return (int) AiUsage::query()
            ->where('user_id', $user->id)
            ->whereDate('usage_date', today())
            ->whereNotIn('feature', self::SYSTEM_FEATURES)
            ->sum('request_count');
    }

    /** @return array{used: int, limit: ?int, remaining: ?int} */
    public function status(User $user): array
    {
        $limit = $this->limitFor($user);
        $used = $this->usedToday($user);

        return [
            'used' => $used,
            'limit' => $limit,
            'remaining' => $limit === null ? null : max(0, $limit - $used),
        ];
    }

    /** @throws AiQuotaExceededException */
    public function ensureAllowed(User $user): void
    {
        $limit = $this->limitFor($user);

        if ($limit !== null && $this->usedToday($user) >= $limit) {
            throw new AiQuotaExceededException($limit);
        }
    }

    public function record(User $user, string $feature, AiResponse $response): void
    {
        $this->increment($user, $feature, [
            'request_count' => 1,
            'tokens_in' => $response->tokensIn,
            'tokens_out' => $response->tokensOut,
            'cost_estimate' => $this->estimateCost($response),
        ]);
    }

    /** Lượt lỗi không tính vào quota của học sinh, nhưng admin cần thấy để biết provider có vấn đề. */
    public function recordFailure(User $user, string $feature): void
    {
        $this->increment($user, $feature, ['failed_count' => 1]);
    }

    public function estimateCost(AiResponse $response): float
    {
        $pricing = config('ai.pricing');
        // Model trả về có thể kèm hậu tố ngày (gpt-4o-mini-2024-07-18) — so theo tiền tố dài nhất.
        $key = collect(array_keys($pricing))
            ->sortByDesc(fn ($k) => strlen($k))
            ->first(fn ($k) => str_starts_with($response->model, $k));

        $price = $pricing[$key] ?? ['input' => 0, 'output' => 0];

        return ($response->tokensIn * $price['input'] + $response->tokensOut * $price['output']) / 1_000_000;
    }

    /**
     * Cộng dồn nguyên tử bằng INSERT … ON DUPLICATE KEY UPDATE — hai request cùng lúc không ghi đè nhau.
     *
     * @param  array<string, int|float>  $deltas
     */
    private function increment(User $user, string $feature, array $deltas): void
    {
        $columns = ['request_count', 'failed_count', 'tokens_in', 'tokens_out', 'cost_estimate'];
        $values = array_map(fn ($c) => $deltas[$c] ?? 0, $columns);
        $now = now();

        DB::statement(
            'INSERT INTO ai_usage (user_id, usage_date, feature, '.implode(', ', $columns).', created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE '
                .implode(', ', array_map(fn ($c) => "{$c} = {$c} + VALUES({$c})", $columns))
                .', updated_at = VALUES(updated_at)',
            [$user->id, $now->toDateString(), $feature, ...$values, $now, $now],
        );
    }
}
