<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Báo cho người trả tiền khi đơn được hoàn. Email luôn gửi (bằng chứng giao dịch), chuông theo cài đặt. */
class PaymentRefunded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? ['mail'] : ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $p = $this->payment->loadMissing('package');

        return [
            'title' => 'Đơn hàng đã được hoàn tiền',
            'message' => "Đã hoàn {$p->amountLabel()} cho gói {$p->package->name}.",
            'url' => route('payment.show', $p),
            'icon' => 'bi-arrow-counterclockwise',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment->loadMissing('package');

        return (new MailMessage)
            ->subject("Đã hoàn tiền đơn {$p->order_code}")
            ->greeting("Chào {$notifiable->name},")
            ->line("TOÁN AI đã hoàn **{$p->amountLabel()}** cho đơn **{$p->order_code}** (gói {$p->package->name}) về ví MoMo của bạn.")
            ->line('Gói học tương ứng đã được thu hồi. Thời gian tiền về ví tuỳ thuộc MoMo, thường trong vài phút đến vài giờ.')
            ->action('Xem giao dịch', route('payment.show', $p))
            ->line('Cần hỗ trợ thêm, hãy trả lời email này hoặc gửi yêu cầu trong ứng dụng.');
    }
}
