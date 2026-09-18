<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Mail đặt lại mật khẩu, viết tiếng Việt cho học sinh / phụ huynh đọc.
 * KHÔNG đưa vào queue: người dùng đang đứng chờ mail, worker chết là hỏng luồng lấy lại tài khoản.
 */
class ResetPasswordLink extends Notification
{
    public function __construct(public readonly string $token) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $minutes = config('auth.passwords.'.config('auth.defaults.passwords').'.expire');

        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu TOÁN AI')
            ->greeting("Chào {$notifiable->name},")
            ->line('Chúng tôi nhận được yêu cầu đặt lại mật khẩu cho tài khoản này.')
            ->action('Đặt mật khẩu mới', $url)
            ->line("Link chỉ dùng được trong {$minutes} phút và chỉ dùng được một lần.")
            ->line('Nếu bạn không yêu cầu đổi mật khẩu, hãy bỏ qua email này — mật khẩu hiện tại vẫn giữ nguyên.')
            ->salutation('Thân mến, đội ngũ TOÁN AI');
    }
}
