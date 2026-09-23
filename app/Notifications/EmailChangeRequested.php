<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Báo về ĐỊA CHỈ CŨ rằng có người vừa yêu cầu đổi email.
 *
 * Gửi ngay lúc yêu cầu, không đợi xác nhận: nếu ai đó chiếm được phiên đăng nhập và định
 * đổi email để khoá chủ tài khoản ra ngoài, đây là cảnh báo duy nhất người chủ nhận được —
 * kèm link huỷ để chặn lại mà không cần đăng nhập.
 */
class EmailChangeRequested extends Notification
{
    public const EXPIRES_MINUTES = 60 * 24 * 7;

    public function __construct(public readonly string $newEmail) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Có yêu cầu đổi email tài khoản TOÁN AI')
            ->greeting("Chào {$notifiable->name},")
            ->line("Vừa có yêu cầu đổi email tài khoản từ **{$notifiable->email}** sang **{$this->newEmail}**.")
            ->line('Nếu đúng là bạn: mở hộp thư của địa chỉ mới và bấm link xác nhận trong đó. Chưa xác nhận thì tài khoản vẫn dùng địa chỉ này.')
            ->action('Không phải tôi — huỷ yêu cầu', $this->cancelUrl($notifiable))
            ->line('Nên đổi mật khẩu ngay nếu bạn không phải người yêu cầu.')
            ->salutation('Thân mến, đội ngũ TOÁN AI');
    }

    private function cancelUrl(object $notifiable): string
    {
        // Link sống 7 ngày và không cần đăng nhập: người bị chiếm tài khoản có thể đã không vào được nữa.
        return URL::temporarySignedRoute('email-change.cancel', now()->addMinutes(self::EXPIRES_MINUTES), [
            'id' => $notifiable->getKey(),
            'hash' => sha1($this->newEmail),
        ]);
    }
}
