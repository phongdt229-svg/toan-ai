<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Referral\ReferralService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cookie;

/**
 * Link giới thiệu `/gioi-thieu/{mã}`: chỉ đặt cookie chứa MÃ rồi đưa tới trang đăng ký học sinh.
 * Không lộ tên người giới thiệu cho người lạ (link có thể bị đăng công khai).
 */
class ReferralLinkController extends Controller
{
    public function __invoke(string $code, ReferralService $referrals): RedirectResponse
    {
        $redirect = redirect()->route('register.student');

        if ($referrals->enabled() && $referrals->referrerByCode($code)) {
            $redirect->withCookie(Cookie::make(ReferralService::COOKIE, strtoupper($code), ReferralService::COOKIE_DAYS * 24 * 60));
        }

        return $redirect;
    }
}
