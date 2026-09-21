<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Xác thực email (§29). Không chặn người dùng học — chỉ chặn những việc cần email thật
 * (mua gói) và nhắc bằng một dải thông báo trong ứng dụng.
 */
class EmailVerificationController extends Controller
{
    /** Trang "hãy kiểm tra hộp thư", nơi middleware `verified` đẩy người dùng tới. */
    public function notice(Request $request): RedirectResponse|View
    {
        if ($request->user()->hasVerifiedEmail()) {
            return redirect()->to($request->user()->homeRoute());
        }

        return view('auth.verify-email');
    }

    /** Link trong mail: đã ký (middleware `signed`) nên không sửa được id hay hash. */
    public function verify(Request $request, int $id, string $hash): RedirectResponse
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals($hash, sha1($user->getEmailForVerification())), 403);

        if ($user->hasVerifiedEmail()) {
            return $this->done($request, $user, 'Email này đã được xác thực từ trước.');
        }

        $user->markEmailAsVerified();
        event(new Verified($user));

        return $this->done($request, $user, 'Đã xác thực email. Cảm ơn bạn!');
    }

    /** Gửi lại mail xác thực; throttle đặt ở route. */
    public function send(Request $request): RedirectResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return back();
        }

        $request->user()->sendEmailVerificationNotification();

        return back()->with('status', 'Đã gửi lại email xác thực tới '.$request->user()->email.'.');
    }

    /**
     * Người bấm link có thể đang đăng nhập bằng tài khoản khác, hoặc chưa đăng nhập
     * (mở mail trên máy khác) — chỉ đưa về đúng chỗ, không tự đăng nhập hộ ai.
     */
    private function done(Request $request, User $user, string $message): RedirectResponse
    {
        $target = $request->user()?->is($user)
            ? $user->homeRoute()
            : route('login');

        return redirect()->to($target)->with('status', $message);
    }
}
