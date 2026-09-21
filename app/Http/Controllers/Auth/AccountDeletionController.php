<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\Auth\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use RuntimeException;

class AccountDeletionController extends Controller
{
    public function __construct(private readonly AccountDeletionService $deletion) {}

    public function destroy(Request $request): RedirectResponse
    {
        $data = $request->validate([
            // Hỏi lại mật khẩu: đây là thao tác không lấy lại được, và chặn người khác
            // dùng máy đang mở sẵn để xoá tài khoản hộ.
            'password' => ['required', 'string'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [], ['password' => 'mật khẩu', 'reason' => 'lý do']);

        $user = $request->user();

        if (! Hash::check($data['password'], $user->password)) {
            return back()->withErrors(['password' => 'Mật khẩu không đúng.']);
        }

        try {
            $this->deletion->request($user, $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status',
            'Đã nhận yêu cầu xoá tài khoản. Dữ liệu cá nhân sẽ được xoá sau '
            .AccountDeletionService::GRACE_DAYS.' ngày; đổi ý trong thời gian đó thì liên hệ '
            .config('site.email').'.');
    }
}
