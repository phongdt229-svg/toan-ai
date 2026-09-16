<?php

namespace App\Services;

use App\Models\Lesson;
use App\Models\User;

/**
 * Tầng thứ ba của phân quyền (PROJECT_PLAN.md §5): user có gói đủ cao để xem
 * nội dung này không. Permission và Policy trả lời "được làm hành động gì",
 * service này trả lời "được xem nội dung nào".
 *
 * Phase 2: chưa có bảng subscriptions nên mọi user đều ở tier `free`.
 * Phase 8 sẽ thay `currentTier()` bằng truy vấn subscription đang active.
 */
class AccessControlService
{
    /** Thứ bậc gói — số lớn hơn bao trùm số nhỏ hơn. */
    private const TIER_RANK = [
        Lesson::ACCESS_FREE => 0,
        Lesson::ACCESS_PRO => 1,
        Lesson::ACCESS_PREMIUM => 2,
    ];

    public function currentTier(?User $user): string
    {
        if (! $user) {
            return Lesson::ACCESS_FREE;
        }

        // Admin và giáo viên phải xem được toàn bộ nội dung để soạn và kiểm duyệt.
        if ($user->isAdmin() || $user->isTeacher()) {
            return Lesson::ACCESS_PREMIUM;
        }

        // TODO(Phase 8): đọc subscription active của user.
        return Lesson::ACCESS_FREE;
    }

    public function canAccessLevel(?User $user, string $requiredLevel): bool
    {
        $userRank = self::TIER_RANK[$this->currentTier($user)] ?? 0;
        $requiredRank = self::TIER_RANK[$requiredLevel] ?? 0;

        return $userRank >= $requiredRank;
    }

    public function canAccessLesson(?User $user, Lesson $lesson): bool
    {
        return $this->canAccessLevel($user, $lesson->access_level);
    }
}
