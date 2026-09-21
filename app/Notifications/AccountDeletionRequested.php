<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Xác nhận đã nhận yêu cầu xoá tài khoản, kèm hạn chót đổi ý. Không qua queue để gửi ngay. */
class AccountDeletionRequested extends Notification
{
    public function __construct(public readonly Carbon $purgeAt) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Đã nhận yêu cầu xoá tài khoản TOÁN AI')
            ->greeting("Chào {$notifiable->name},")
            ->line('Chúng tôi đã nhận yêu cầu xoá tài khoản của bạn. Tài khoản đã ngừng truy cập ngay từ bây giờ.')
            ->line('Dữ liệu cá nhân sẽ bị xoá vĩnh viễn vào ngày **'.$this->purgeAt->format('d/m/Y').'**.')
            ->line('Đổi ý trước ngày đó thì liên hệ '.config('site.email').' để chúng tôi khôi phục.')
            ->line('Hoá đơn và dữ liệu giao dịch được giữ theo thời hạn kế toán mà pháp luật yêu cầu.')
            ->salutation('Thân mến, đội ngũ TOÁN AI');
    }
}
