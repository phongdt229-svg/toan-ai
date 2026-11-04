<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Công tắc tạm ẩn chức năng giáo viên (TEACHER_PORTAL_ENABLED, xem config/site.php).
 * Chỉ ẩn và chặn lối vào, KHÔNG xoá gì: bật lại là tài khoản, lớp, bài giao còn nguyên.
 *
 * Chỉ chặn người CHỈ có vai trò giáo viên: route `teacher.*` cũng cho admin (role:teacher,admin)
 * và admin vẫn cần soạn bài, ngân hàng câu hỏi ở đó.
 */
class TeacherPortal
{
    public const CLOSED_MESSAGE = 'Chức năng giáo viên đang tạm đóng. Vui lòng quay lại sau hoặc gửi yêu cầu hỗ trợ.';

    public static function enabled(): bool
    {
        return (bool) config('site.teacher_portal');
    }

    public static function blocks(User $user): bool
    {
        return ! self::enabled() && $user->isTeacher() && ! $user->isAdmin();
    }

    /**
     * Bài hướng dẫn được phép hiện — một nguồn cho trang hướng dẫn, sitemap và llms.txt,
     * để Google/công cụ AI không giới thiệu chức năng đang đóng.
     *
     * @return Collection<string, array<string, mixed>>
     */
    public static function guides(): Collection
    {
        return collect(config('guides.articles', []))
            ->when(! self::enabled(), fn (Collection $articles) => $articles->reject(fn (array $a) => $a['audience'] === 'teacher'));
    }

    /** @return array<string, array<string, mixed>> */
    public static function guideAudiences(): array
    {
        return collect(config('guides.audiences', []))
            ->when(! self::enabled(), fn (Collection $audiences) => $audiences->except('teacher'))
            ->all();
    }
}
