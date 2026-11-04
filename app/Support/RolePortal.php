<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Công tắc tạm ẩn cổng theo vai trò (config `site.portals`, env TEACHER_PORTAL_ENABLED / PARENT_PORTAL_ENABLED).
 * Chỉ ẩn và chặn lối vào, KHÔNG xoá gì: bật lại là tài khoản, liên kết, lớp, bài giao còn nguyên.
 *
 * Admin không bao giờ bị chặn: route `teacher.*` cũng cho admin (role:teacher,admin) để soạn bài.
 */
class RolePortal
{
    private const LABELS = ['teacher' => 'giáo viên', 'parent' => 'phụ huynh'];

    /** Vai trò chưa khai công tắc (học sinh, admin) luôn mở. */
    public static function enabled(?string $role): bool
    {
        return $role === null || (bool) config("site.portals.{$role}", true);
    }

    public static function blocks(User $user): bool
    {
        return self::closedRole($user) !== null;
    }

    public static function closedMessage(User $user): string
    {
        return 'Chức năng '.(self::LABELS[self::closedRole($user)] ?? 'này').' đang tạm đóng. Vui lòng quay lại sau hoặc gửi yêu cầu hỗ trợ.';
    }

    /**
     * Bài hướng dẫn được phép hiện — một nguồn cho trang hướng dẫn, sitemap và llms.txt, để Google/công cụ AI
     * không giới thiệu chức năng đang đóng. Bài của đối tượng khác mà nói về cổng đó thì khai `'portal' => 'parent'`.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function guides(): Collection
    {
        return collect(config('guides.articles', []))
            ->filter(fn (array $a) => self::enabled($a['portal'] ?? null) && self::enabled($a['audience']));
    }

    /** @return array<string, array<string, mixed>> */
    public static function guideAudiences(): array
    {
        return collect(config('guides.audiences', []))
            ->filter(fn (array $meta, string $audience) => self::enabled($audience))
            ->all();
    }

    private static function closedRole(User $user): ?string
    {
        if ($user->isAdmin()) {
            return null;
        }

        return match (true) {
            $user->isTeacher() && ! self::enabled('teacher') => 'teacher',
            $user->isParent() && ! self::enabled('parent') => 'parent',
            default => null,
        };
    }
}
