<?php

namespace App\Notifications;

use App\Models\Payment;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Gửi qua queue: IPN phải trả lời MoMo nhanh, không chờ SMTP. */
class PaymentSucceeded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment) {}

    /**
     * Email biên nhận thanh toán luôn gửi (không cho tắt — bằng chứng giao dịch); chỉ chuông
     * trong app theo được cài đặt bật/tắt.
     *
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? ['mail'] : ['mail', 'database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $p = $this->payment->loadMissing('package');

        return [
            'title' => 'Thanh toán thành công',
            'message' => "Đã nhận {$p->amountLabel()} cho gói {$p->package->name}.",
            'url' => route('payment.show', $p),
            'icon' => 'bi-cash-coin',
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $p = $this->payment->loadMissing('package', 'subscription.user');
        $sub = $p->subscription;

        return (new MailMessage)
            ->subject("Thanh toán thành công — {$p->package->name}")
            ->greeting("Chào {$notifiable->name},")
            ->line("TOÁN AI đã nhận {$p->amountLabel()} cho đơn **{$p->order_code}**.")
            ->line("Gói **{$p->package->name}** cho **{$sub->user->name}** có hiệu lực từ {$sub->starts_at->format('d/m/Y')} đến {$sub->ends_at->format('d/m/Y')}.")
            ->action('Xem giao dịch', route('payment.show', $p))
            ->line('Cảm ơn bạn đã đồng hành cùng TOÁN AI!');
    }
}
