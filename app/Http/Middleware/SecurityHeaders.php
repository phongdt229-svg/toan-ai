<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header bảo mật cho mọi response (§29).
 *
 * CSP chỉ khoá những thứ an toàn để khoá (nhúng iframe, plugin, base, form-action) — chưa chặn script inline
 * vì nhiều trang còn dùng <script> inline; muốn siết script-src phải chuyển hết sang file JS có nonce trước.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // Không trang nào cần camera/micro/vị trí; thanh toán do MoMo xử lý ở domain của họ.
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(), payment=()',
            'Cross-Origin-Opener-Policy' => 'same-origin',
            // form-action cần *.momo.vn: Chrome áp form-action cho cả redirect sau POST "Thanh toán".
            'Content-Security-Policy' => "frame-ancestors 'self'; object-src 'none'; base-uri 'self'; form-action 'self' https://*.momo.vn",
        ];

        // HSTS chỉ khi đang chạy HTTPS thật — gửi qua HTTP ở local sẽ làm trình duyệt kẹt HTTPS với localhost.
        if ($request->isSecure()) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        // Trang có dữ liệu cá nhân không được cache ở proxy/CDN dùng chung.
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'no-store, private');
        }

        return $response;
    }
}
