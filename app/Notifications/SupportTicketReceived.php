<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Báo cho hộp thư hỗ trợ khi có yêu cầu mới. Qua queue để người gửi không phải chờ SMTP. */
class SupportTicketReceived extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $t = $this->ticket;

        return (new MailMessage)
            ->subject("[{$t->code}] {$t->typeLabel()}: ".Str::limit($t->subject, 60))
            ->line("**{$t->typeLabel()}** từ {$t->name} ({$t->email})".($t->user_id ? ' — tài khoản đã đăng nhập' : ' — khách'))
            ->line('**Tiêu đề:** '.$t->subject)
            ->line($t->message)
            ->when($t->context_url, fn (MailMessage $mail) => $mail->line('**Trang liên quan:** '.$t->context_url))
            ->action('Mở trong trang quản trị', route('admin.support.show', $t))
            ->line('Mã yêu cầu: '.$t->code);
    }
}
