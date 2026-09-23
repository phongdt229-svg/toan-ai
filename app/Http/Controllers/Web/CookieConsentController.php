<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;

/**
 * Lựa chọn cookie của người dùng (PROJECT_PLAN.md §10, đợt 23/09).
 *
 * Lưu bằng cookie của Laravel qua POST chứ không phải `document.cookie`: chạy được cả khi tắt JS,
 * và đi qua EncryptCookies nên không sửa tay được. Server đọc cookie này để quyết định
 * CÓ RENDER gtag/GTM hay không — chặn từ đầu, vì nạp script rồi mới tắt là muộn.
 */
class CookieConsentController extends Controller
{
    public const COOKIE = 'cookie_consent';

    public const ACCEPTED = 'accepted';

    public const REJECTED = 'rejected';

    /**
     * "Đã hiểu" của thông báo cookie thường — lưu ở cookie RIÊNG, không dùng chung với lựa chọn đo lường.
     * Người dùng mới chỉ đọc một thông báo, chưa đồng ý gì cả — sau này bật GA thì vẫn phải hỏi họ.
     */
    public const SEEN = 'seen';

    public const NOTICE_COOKIE = 'cookie_notice';

    /** Hỏi lại sau 180 ngày. */
    private const MINUTES = 60 * 24 * 180;

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'choice' => ['required', 'in:'.self::ACCEPTED.','.self::REJECTED.','.self::SEEN],
        ]);

        if ($data['choice'] === self::SEEN) {
            Cookie::queue(Cookie::make(self::NOTICE_COOKIE, '1', self::MINUTES));

            return back();
        }

        Cookie::queue(Cookie::make(self::COOKIE, $data['choice'], self::MINUTES));

        return back()->with('status', $data['choice'] === self::ACCEPTED
            ? 'Cảm ơn bạn. Bạn đổi lại lựa chọn bất cứ lúc nào ở chân trang.'
            : 'Đã tắt cookie phân tích. Chỉ còn cookie cần thiết để bạn đăng nhập.');
    }

    /** Xoá lựa chọn để dải hỏi hiện lại — link "Cài đặt cookie" ở chân trang. */
    public function destroy(): RedirectResponse
    {
        Cookie::queue(Cookie::forget(self::COOKIE));
        Cookie::queue(Cookie::forget(self::NOTICE_COOKIE));

        return back()->with('status', 'Hãy chọn lại bên dưới.');
    }
}
