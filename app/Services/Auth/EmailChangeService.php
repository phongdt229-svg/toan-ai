<?php

namespace App\Services\Auth;

use App\Models\User;
use App\Notifications\ConfirmNewEmail;
use App\Notifications\EmailChangeRequested;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use RuntimeException;

/**
 * Đổi email tài khoản (PROJECT_PLAN.md §10, đợt 23/09).
 *
 * Nguyên tắc: **không đổi thẳng**. Địa chỉ mới nằm chờ ở `pending_email` cho tới khi người dùng
 * bấm link gửi tới chính địa chỉ đó. Gõ nhầm một ký tự mà đổi ngay là mất luôn đường đăng nhập
 * và đường đặt lại mật khẩu — hỏng vĩnh viễn, đúng cái hố mà đợt xác thực email 21/09 đào ra.
 *
 * Địa chỉ cũ luôn được báo ngay lúc yêu cầu, kèm link huỷ: đó là cảnh báo duy nhất
 * khi có người chiếm phiên đăng nhập và định khoá chủ tài khoản ra ngoài.
 */
class EmailChangeService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @throws RuntimeException */
    public function request(User $user, string $newEmail): void
    {
        $newEmail = mb_strtolower(trim($newEmail));

        if ($newEmail === mb_strtolower($user->email)) {
            throw new RuntimeException('Đây đang là email của bạn rồi.');
        }

        if ($this->takenBySomeoneElse($newEmail, $user)) {
            throw new RuntimeException('Email này đã có người dùng.');
        }

        $user->forceFill(['pending_email' => $newEmail, 'pending_email_at' => now()])->save();

        $this->audit->log('email.change_requested', $user, ['email' => $user->email], ['pending_email' => $newEmail]);

        // Thư xác nhận phải đi tới ĐỊA CHỈ MỚI — gửi bằng $user->notify() là nó đi về
        // địa chỉ cũ, và cả luồng xác nhận mất sạch ý nghĩa.
        Notification::route('mail', $newEmail)->notify(new ConfirmNewEmail($user, $newEmail));

        // Cảnh báo về địa chỉ CŨ (vẫn là $user->email vì chưa đổi gì).
        $user->notify(new EmailChangeRequested($newEmail));
    }

    /**
     * Người dùng bấm link trong hộp thư địa chỉ mới.
     *
     * @throws RuntimeException
     */
    public function confirm(User $user, string $hash): void
    {
        $pending = $user->pending_email;

        if (! $pending || ! hash_equals(sha1($pending), $hash)) {
            throw new RuntimeException('Link không còn hiệu lực. Hãy yêu cầu đổi email lại.');
        }

        // Kiểm lại lúc xác nhận: giữa hai bước có thể có người khác đã đăng ký địa chỉ này.
        if ($this->takenBySomeoneElse($pending, $user)) {
            $this->clear($user);

            throw new RuntimeException('Trong lúc chờ xác nhận đã có người dùng email này.');
        }

        $old = $user->email;

        DB::transaction(function () use ($user, $pending) {
            $user->forceFill([
                'email' => $pending,
                // Bấm được link nghĩa là địa chỉ mới có thật — không bắt xác thực thêm lần nữa.
                'email_verified_at' => now(),
                'pending_email' => null,
                'pending_email_at' => null,
            ])->save();
        });

        $this->audit->log('email.changed', $user, ['email' => $old], ['email' => $pending]);
    }

    /** Chủ địa chỉ cũ bấm "Không phải tôi", hoặc người dùng tự bỏ yêu cầu. */
    public function cancel(User $user, ?string $hash = null): void
    {
        if ($hash !== null && (! $user->pending_email || ! hash_equals(sha1($user->pending_email), $hash))) {
            throw new RuntimeException('Yêu cầu này không còn nữa.');
        }

        $pending = $user->pending_email;
        $this->clear($user);

        if ($pending) {
            $this->audit->log('email.change_cancelled', $user, ['pending_email' => $pending], null);
        }
    }

    /** Quản trị đổi thẳng — dùng khi người dùng mất luôn quyền vào hộp thư cũ lẫn mới. */
    public function changeByAdmin(User $user, string $newEmail, User $admin): void
    {
        $newEmail = mb_strtolower(trim($newEmail));

        if ($this->takenBySomeoneElse($newEmail, $user)) {
            throw new RuntimeException('Email này đã có người dùng.');
        }

        $old = $user->email;

        $user->forceFill([
            'email' => $newEmail,
            // Quản trị đổi hộ thì KHÔNG coi là đã xác thực: người dùng phải tự mở hộp thư mới.
            'email_verified_at' => null,
            'pending_email' => null,
            'pending_email_at' => null,
        ])->save();

        $this->audit->log('email.changed_by_admin', $user, ['email' => $old], ['email' => $newEmail, 'by' => $admin->id]);

        $user->sendEmailVerificationNotification();
    }

    private function clear(User $user): void
    {
        $user->forceFill(['pending_email' => null, 'pending_email_at' => null])->save();
    }

    private function takenBySomeoneElse(string $email, User $user): bool
    {
        // withTrashed: tài khoản đang chờ xoá vẫn giữ chỗ địa chỉ, chưa ẩn danh thì chưa nhả ra.
        return User::withTrashed()->where('email', $email)->whereKeyNot($user->id)->exists();
    }
}
