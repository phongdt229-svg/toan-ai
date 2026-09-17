<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\Package;
use App\Models\User;

/**
 * Tầng thứ ba của phân quyền (PROJECT_PLAN.md §5): user có gói đủ cao để xem
 * nội dung này không. Permission và Policy trả lời "được làm hành động gì",
 * service này trả lời "được xem nội dung nào".
 */
class AccessControlService
{
    public function __construct(private readonly SubscriptionService $subscriptions) {}

    public function currentTier(?User $user): string
    {
        if (! $user) {
            return Package::TIER_FREE;
        }

        // Admin và giáo viên phải xem được toàn bộ nội dung để soạn và kiểm duyệt.
        if ($user->isAdmin() || $user->isTeacher()) {
            return Package::TIER_PREMIUM;
        }

        return $this->subscriptions->tier($user);
    }

    public function canAccessLevel(?User $user, string $requiredLevel): bool
    {
        $userRank = Package::TIER_RANK[$this->currentTier($user)] ?? 0;
        $requiredRank = Package::TIER_RANK[$requiredLevel] ?? 0;

        return $userRank >= $requiredRank;
    }

    public function canAccessLesson(?User $user, Lesson $lesson): bool
    {
        return $this->canAccessLevel($user, $lesson->access_level);
    }

    /** Tính năng bật/tắt theo gói (package_features). Giáo viên/admin luôn được. */
    public function allows(?User $user, string $featureKey): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->isAdmin() || $user->isTeacher()) {
            return true;
        }

        return $this->subscriptions->allows($user, $featureKey);
    }
}
