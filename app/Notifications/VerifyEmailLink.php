<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

/**
 * Mail xác thực email, bản tiếng Việt thay cho mail mặc định của Laravel.
 * KHÔNG qua queue: người vừa đăng ký đang đứng chờ thư, worker chết là hỏng luồng.
 */
class VerifyEmailLink extends Notification
{
    /** Link sống 60 phút — đủ để mở hộp thư, không để lang thang mãi trong inbox. */
    private const EXPIRES_MINUTES = 60;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Xác thực email TOÁN AI')
            ->greeting("Chào {$notifiable->name},")
            ->line('Bấm nút bên dưới để xác thực email cho tài khoản TOÁN AI của bạn.')
            ->action('Xác thực email', $this->verificationUrl($notifiable))
            ->line('Link dùng được một lần trong '.self::EXPIRES_MINUTES.' phút.')
            ->line('Xác thực xong bạn mới nhận được email đặt lại mật khẩu, báo cáo học tập và biên nhận thanh toán.')
            ->line('Nếu bạn không đăng ký tài khoản nào, hãy bỏ qua email này.')
            ->salutation('Thân mến, đội ngũ TOÁN AI');
    }

    private function verificationUrl(object $notifiable): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(self::EXPIRES_MINUTES), [
            'id' => $notifiable->getKey(),
            // Hash theo email hiện tại: đổi email thì link cũ hết tác dụng.
            'hash' => sha1($notifiable->getEmailForVerification()),
        ]);
    }
}
