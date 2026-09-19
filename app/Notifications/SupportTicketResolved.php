<?php

namespace App\Notifications;

use App\Models\SupportTicket;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Yêu cầu hỗ trợ của người dùng vừa được xử lý xong. Chỉ gửi khi ticket gắn với tài khoản
 * đã đăng nhập — khách vãng lai không có gì để "trong app" nhận. */
class SupportTicketResolved extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly SupportTicket $ticket) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Yêu cầu hỗ trợ đã xử lý',
            'message' => "Yêu cầu {$this->ticket->code} (\"{$this->ticket->subject}\") đã được xử lý xong.",
            'url' => null,
            'icon' => 'bi-headset',
        ];
    }
}
