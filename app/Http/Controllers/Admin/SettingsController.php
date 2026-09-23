<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AccountService;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly AccountService $account,
        private readonly TwoFactorService $twoFactor,
    ) {}

    public function edit(Request $request): View
    {
        $user = $request->user();
        $enabled = $this->twoFactor->isEnabled($user);

        // Đang cài dở (có secret, chưa xác nhận): hiện QR để quét. Đã bật thì không bao giờ hiện lại secret.
        $secret = ! $enabled ? $this->twoFactor->secretOf($user) : null;

        return view('admin.settings', [
            'user' => $user,
            'twoFactorEnabled' => $enabled,
            'twoFactorSecret' => $secret,
            'twoFactorQr' => $secret ? $this->twoFactor->qrSvg($this->twoFactor->otpAuthUrl($user, $secret)) : null,
        ]);
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $this->account->updateProfile($request->user(), $request->validated());

        return back()->with('status', 'Đã lưu hồ sơ.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->account->updatePassword($request->user(), $request->validated('password'));

        return back()->with('status', 'Đã đổi mật khẩu.');
    }
}
