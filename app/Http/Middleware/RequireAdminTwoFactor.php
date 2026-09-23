<?php

namespace App\Http\Middleware;

use App\Services\Auth\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Bật bằng ADMIN_REQUIRE_2FA=true: admin chưa cài 2FA chỉ vào được trang Cài đặt để cài.
 * Mặc định tắt để không khoá tài khoản demo/CI; production nên bật sau khi mọi admin đã cài.
 */
class RequireAdminTwoFactor
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (config('auth.require_admin_2fa') && $user && ! $this->twoFactor->isEnabled($user)
            && ! $request->routeIs('admin.settings', 'admin.two-factor.*')) {
            return redirect()->route('admin.settings')
                ->with('error', 'Tài khoản quản trị bắt buộc bật xác thực 2 bước. Hãy cài đặt ở phần "Xác thực 2 bước" bên dưới.');
        }

        return $next($request);
    }
}
