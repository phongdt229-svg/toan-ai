<?php

namespace App\Http\Middleware;

use App\Services\Admin\ImpersonationService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Trong lúc admin đăng nhập hộ, chặn những việc không thể đổi ý: chiếm tài khoản (mật khẩu, email),
 * xoá tài khoản, và tiêu tiền. Danh sách route ở đây thay vì rải middleware từng route để thêm route
 * mới dễ nhớ — chỉ cần thêm một tên vào đây.
 */
class BlockWhenImpersonating
{
    private const BLOCKED = [
        '*.settings.password', 'email-change.*', 'account.destroy',
        'packages.checkout', 'packages.pay', 'packages.voucher.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if ($request->session()->has(ImpersonationService::SESSION_KEY)
            && ! $request->isMethodSafe()
            && $request->routeIs(...self::BLOCKED)) {
            return back()->with('error', 'Đang đăng nhập hộ — không được thực hiện thao tác này.');
        }

        return $next($request);
    }
}
