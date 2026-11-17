<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Bật chế độ debug của Laravel cho RIÊNG một trình duyệt ở production.
 *
 * Tại sao: APP_DEBUG=true cho cả site là phơi stack trace (đường dẫn, câu SQL, dữ liệu học sinh)
 * cho mọi khách. Cookie này chỉ đặt được qua /_diag.php?token=…&debug=1, nên chỉ người cầm
 * DIAG_TOKEN mới thấy lỗi chi tiết; DIAG_TOKEN trống là tắt hẳn.
 */
final class DiagnosticDebug
{
    public const COOKIE = 'diag_debug';

    /** Token ngắn dễ đoán thì coi như chưa đặt — cùng ngưỡng với public/_diag.php. */
    public const MIN_TOKEN_LENGTH = 32;

    public static function allows(Request $request, ?string $token): bool
    {
        $token = (string) $token;
        // Cookie đọc ở dạng thô: hàm này chạy lúc đăng ký provider, trước EncryptCookies —
        // và _diag.php (ngoài Laravel) cũng không mã hoá được cookie.
        $given = (string) $request->cookies->get(self::COOKIE, '');

        return strlen($token) >= self::MIN_TOKEN_LENGTH && $given !== '' && hash_equals($token, $given);
    }
}
