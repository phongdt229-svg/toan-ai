<?php

namespace App\Services\Rewards;

use App\Models\RewardPoint;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\Learning\StreakService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/** Trao huy hiệu theo config/badges.php — gọi sau mỗi lần cộng điểm. Trao rồi thì không thu lại. */
class BadgeService
{
    public function __construct(private readonly StreakService $streaks) {}

    /** @return array<int, string> khoá các huy hiệu vừa trao lần này */
    public function evaluate(User $user): array
    {
        $owned = UserBadge::where('user_id', $user->id)->pluck('badge_key')->flip();
        $pending = collect(config('badges', []))->reject(fn ($badge, $key) => $owned->has($key));

        if ($pending->isEmpty()) {
            return [];
        }

        $counts = RewardPoint::where('user_id', $user->id)
            ->selectRaw('reason, COUNT(*) as n')->groupBy('reason')->pluck('n', 'reason');
        $total = (int) RewardPoint::where('user_id', $user->id)->sum('points');
        // Chuỗi ngày quét 400 ngày hoạt động — chỉ tính khi còn huy hiệu chuỗi chưa đạt.
        $bestStreak = $pending->contains(fn ($b) => $b['rule'][0] === 'streak')
            ? $this->streaks->forStudent($user)['best']
            : 0;

        $earned = $pending->filter(fn ($badge) => match ($badge['rule'][0]) {
            'count' => (int) ($counts[$badge['rule'][1]] ?? 0) >= $badge['rule'][2],
            'streak' => $bestStreak >= $badge['rule'][1],
            'points' => $total >= $badge['rule'][1],
            default => false,
        })->keys();

        if ($earned->isEmpty()) {
            return [];
        }

        $now = now();
        UserBadge::insertOrIgnore($earned->map(fn ($key) => [
            'user_id' => $user->id, 'badge_key' => $key, 'earned_at' => $now,
        ])->all());

        return $earned->all();
    }

    /**
     * Mọi huy hiệu kèm trạng thái đạt/chưa — cho trang Thành tích.
     *
     * @return Collection<string, array{name: string, description: string, icon: string, earned_at: ?Carbon}>
     */
    public function catalogFor(User $user): Collection
    {
        $owned = UserBadge::where('user_id', $user->id)->get()->keyBy('badge_key');

        return collect(config('badges', []))->map(fn ($badge, $key) => [
            'name' => $badge['name'],
            'description' => $badge['description'],
            'icon' => $badge['icon'],
            'earned_at' => $owned->get($key)?->earned_at,
        ]);
    }
}
