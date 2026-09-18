<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ForgotPasswordRequest;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\PasswordResetService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class PasswordResetController extends Controller
{
    /** Thông báo chung cho mọi trường hợp — không cho biết email nào có tài khoản (§29). */
    private const SENT_MESSAGE = 'Nếu email này có tài khoản, chúng tôi đã gửi link đặt lại mật khẩu. '
        .'Hãy kiểm tra hộp thư (kể cả mục spam).';

    public function __construct(private readonly PasswordResetService $passwords) {}

    public function request(): View
    {
        return view('auth.forgot-password');
    }

    public function email(ForgotPasswordRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $this->passwords->sendLink($request->string('email')->toString());

        return back()->with('status', self::SENT_MESSAGE);
    }

    public function reset(Request $request, string $token): View
    {
        return view('auth.reset-password', [
            'token' => $token,
            'email' => $request->string('email')->toString(),
        ]);
    }

    public function update(ResetPasswordRequest $request): RedirectResponse
    {
        $status = $this->passwords->reset($request->validated());

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages([
                'email' => 'Link đặt lại mật khẩu không đúng hoặc đã hết hạn. Hãy yêu cầu link mới.',
            ]);
        }

        return redirect()->route('login')
            ->with('status', 'Đã đổi mật khẩu. Bạn đăng nhập bằng mật khẩu mới nhé.');
    }
}
