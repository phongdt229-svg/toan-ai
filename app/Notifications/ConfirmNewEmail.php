<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Gửi TỚI ĐỊA CHỈ MỚI để xác nhận đổi email.
 *
 * Đây là lý do cả luồng tồn tại: nếu đổi thẳng thì gõ nhầm một ký tự là người dùng mất
 * đường đăng nhập lẫn đường đặt lại mật khẩu. Bấm được link này nghĩa là địa chỉ mới có thật
 * và đúng là của họ.
 *
 * Nhận `$user` qua constructor chứ không đọc `$notifiable`: thông báo này gửi qua
 * `Notification::route('mail', ...)` nên `$notifiable` là địa chỉ trống, không có tên hay id.
 *
 * KHÔNG qua queue: người dùng đang ngồi chờ thư ngay lúc đó.
 */
class ConfirmNewEmail extends Notification
{
    public const EXPIRES_MINUTES = 60;

    public function __construct(
        public readonly User $user,
        public readonly string $newEmail,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Xác nhận email mới cho tài khoản TOÁN AI')
            ->greeting("Chào {$this->user->name},")
            ->line("Có yêu cầu đổi email tài khoản TOÁN AI sang **{$this->newEmail}**.")
            ->action('Xác nhận email mới', $this->confirmUrl())
            ->line('Link dùng được một lần trong '.self::EXPIRES_MINUTES.' phút.')
            ->line('Xác nhận xong bạn đăng nhập bằng địa chỉ mới này.')
            ->line('Nếu bạn không yêu cầu đổi, hãy bỏ qua email này — tài khoản vẫn giữ nguyên địa chỉ cũ.')
            ->salutation('Thân mến, đội ngũ TOÁN AI');
    }

    private function confirmUrl(): string
    {
        return URL::temporarySignedRoute('email-change.confirm', now()->addMinutes(self::EXPIRES_MINUTES), [
            'id' => $this->user->getKey(),
            // Hash theo địa chỉ MỚI: đổi ý và yêu cầu địa chỉ khác thì link cũ hết tác dụng.
            'hash' => sha1($this->newEmail),
        ]);
    }
}
