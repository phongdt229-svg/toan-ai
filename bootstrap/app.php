<?php

use App\Http\Middleware\EnsureAccountIsActive;
use App\Http\Middleware\EnsureUserHasRole;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

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
            'role' => EnsureUserHasRole::class,
            'active' => EnsureAccountIsActive::class,
        ]);

        // MoMo gọi server-to-server, không có CSRF token (PROJECT_PLAN.md §8).
        $middleware->validateCsrfTokens(except: [
            'api/v1/payment/momo/ipn',
        ]);

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->append(SecurityHeaders::class);

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
        //
    })->create();
