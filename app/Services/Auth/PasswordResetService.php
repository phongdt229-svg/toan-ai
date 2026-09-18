<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;

/**
 * Quên mật khẩu / đặt lại mật khẩu (§29).
 *
 * Hai nguyên tắc bảo mật:
 *  - Không tiết lộ email nào có tài khoản: mọi trường hợp đều trả về cùng một thông báo,
 *    việc "có gửi mail hay không" chỉ quyết định bên trong service.
 *  - Đổi mật khẩu xong thì mọi phiên và token cũ hết hiệu lực (phòng trường hợp mất máy, bị chiếm tài khoản).
 */
class PasswordResetService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** Gửi link đặt lại mật khẩu — im lặng bỏ qua nếu email không có tài khoản dùng được. */
    public function sendLink(string $email): void
    {
        $user = User::where('email', $email)->first();

        // Tài khoản bị khoá / bị từ chối không được lấy lại quyền truy cập qua đường này.
        if (! $user || in_array($user->status, [User::STATUS_SUSPENDED, User::STATUS_REJECTED], true)) {
            return;
        }

        Password::sendResetLink(['email' => $email]);
    }

    /**
     * Đặt mật khẩu mới theo token.
     *
     * @param  array{email: string, password: string, password_confirmation: string, token: string}  $credentials
     * @return string trạng thái của Password broker (PASSWORD_RESET / INVALID_TOKEN / INVALID_USER)
     */
    public function reset(array $credentials): string
    {
        return Password::reset($credentials, function (User $user, string $password) {
            $user->forceFill([
                'password' => Hash::make($password),
                'remember_token' => Str::random(60),
            ])->save();

            $this->revokeOtherSessions($user);

            $this->audit->log('user.password_reset', $user, null, ['email' => $user->email]);

            event(new PasswordReset($user));
        });
    }

    /** Thu hồi token API và xoá phiên đăng nhập cũ của user. */
    private function revokeOtherSessions(User $user): void
    {
        $user->tokens()->delete();

        if (config('session.driver') === 'database') {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
        }
    }
}
