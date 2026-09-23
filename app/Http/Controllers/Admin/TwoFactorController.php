<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/** Bật/tắt xác thực 2 bước cho chính tài khoản đang đăng nhập (trang Cài đặt quản trị). */
class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function enable(Request $request): RedirectResponse
    {
        $this->assertPassword($request);

        if ($this->twoFactor->isEnabled($request->user())) {
            return back()->with('error', 'Xác thực 2 bước đã bật rồi.');
        }

        $this->twoFactor->beginSetup($request->user());

        return back();
    }

    public function confirm(Request $request): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'digits:6']]);

        $codes = $this->twoFactor->confirm($request->user(), $data['code']);

        if ($codes === null) {
            throw ValidationException::withMessages(['code' => 'Mã không đúng. Kiểm tra lại đồng hồ điện thoại rồi thử lại.']);
        }

        // Mã dự phòng chỉ hiện một lần: flash sang request kế tiếp, không lưu ở đâu khác.
        return back()->with('recovery_codes', $codes)->with('status', 'Đã bật xác thực 2 bước.');
    }

    public function regenerate(Request $request): RedirectResponse
    {
        $this->assertPassword($request);

        return back()->with('recovery_codes', $this->twoFactor->regenerateRecoveryCodes($request->user()))
            ->with('status', 'Đã tạo bộ mã dự phòng mới, bộ cũ không còn dùng được.');
    }

    public function disable(Request $request): RedirectResponse
    {
        $this->assertPassword($request);
        $this->twoFactor->disable($request->user());

        return back()->with('status', 'Đã tắt xác thực 2 bước.');
    }

    private function assertPassword(Request $request): void
    {
        $request->validate(['current_password' => ['required', 'string']]);

        if (! Hash::check($request->input('current_password'), $request->user()->password)) {
            throw ValidationException::withMessages(['current_password' => 'Mật khẩu hiện tại không đúng.']);
        }
    }
}
