<?php

use App\Http\Middleware\BlockWhenImpersonating;
use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\RequireAdminTwoFactor;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Sentry\Laravel\Integration;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'verified' => EnsureEmailIsVerified::class,
            'role' => EnsureUserHasRole::class,
            'active' => EnsureAccountIsActive::class,
            'admin.2fa' => RequireAdminTwoFactor::class,
        ]);

        // MoMo gọi server-to-server, không có CSRF token (PROJECT_PLAN.md §8).
        $middleware->validateCsrfTokens(except: [
            'api/v1/payment/momo/ipn',
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        // Vẫn phục vụ khi đang bảo trì:
        // - `up`: health check của load balancer, trả 503 là bị coi như server chết rồi khởi động lại.
        // - `dang-nhap` + `quan-tri/bao-tri`: đường cứu hộ. Admin bật bảo trì rồi mất cookie bỏ qua
        //   (đổi máy, hết 12 giờ) vẫn tự tắt được mà không cần SSH vào máy chủ.
        $middleware->preventRequestsDuringMaintenance(except: [
            'up',
            'dang-nhap',
            'dang-nhap/*',
            'quan-tri/bao-tri',
        ]);

        $middleware->append(SecurityHeaders::class);
        $middleware->appendToGroup('web', BlockWhenImpersonating::class);

        // Giới hạn chung chống cào dữ liệu / spam — các route nhạy cảm có throttle riêng chặt hơn.
        $middleware->appendToGroup('web', 'throttle:global');
        $middleware->appendToGroup('api', 'throttle:global');

        // Sau load balancer / Cloudflare: tin header X-Forwarded-* để biết request là HTTPS và IP thật.
        // Chỉ đặt TRUSTED_PROXIES khi server không nhận kết nối trực tiếp từ internet.
        if ($proxies = env('TRUSTED_PROXIES')) {
            $middleware->trustProxies(at: $proxies === '*' ? '*' : explode(',', $proxies));
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Không có SENTRY_LARAVEL_DSN thì Sentry tự tắt (local/test không gửi gì ra ngoài).
        Integration::handles($exceptions);
    })->create();
