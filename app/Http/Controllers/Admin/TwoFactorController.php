<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\TwoFactorCodeRequest;
use App\Http\Requests\ConfirmPasswordRequest;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;

/** Bật/tắt xác thực 2 bước cho chính tài khoản đang đăng nhập (trang Cài đặt quản trị). */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function enable(ConfirmPasswordRequest $request): RedirectResponse
    {
        if ($this->twoFactor->isEnabled($request->user())) {
            return back()->with('error', 'Xác thực 2 bước đã bật rồi.');
        }

        $this->twoFactor->beginSetup($request->user());

        return back();
    }

    public function confirm(TwoFactorCodeRequest $request): RedirectResponse
    {
        $codes = $this->twoFactor->confirm($request->user(), $request->validated('code'));

        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'Mã không đúng. Kiểm tra lại đồng hồ điện thoại rồi thử lại.']);
        }

        // Mã dự phòng chỉ hiện một lần: flash sang request kế tiếp, không lưu ở đâu khác.
        return back()->with('recovery_codes', $codes)->with('status', 'Đã bật xác thực 2 bước.');
    }

    public function regenerate(ConfirmPasswordRequest $request): RedirectResponse
    {
        return back()->with('recovery_codes', $this->twoFactor->regenerateRecoveryCodes($request->user()))
            ->with('status', 'Đã tạo bộ mã dự phòng mới, bộ cũ không còn dùng được.');
    }

    public function disable(ConfirmPasswordRequest $request): RedirectResponse
    {
        $this->twoFactor->disable($request->user());

        return back()->with('status', 'Đã tắt xác thực 2 bước.');
    }
}
