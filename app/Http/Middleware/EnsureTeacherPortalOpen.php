<?php

namespace App\Http\Middleware;

use App\Support\TeacherPortal;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Trang đăng ký giáo viên: 404 khi đang tạm ẩn, kể cả người giữ link cũ hay gửi POST thẳng. */
class EnsureTeacherPortalOpen
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(TeacherPortal::enabled(), 404);

        return $next($request);
    }
}
