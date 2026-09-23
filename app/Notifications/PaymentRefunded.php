<?php

namespace App\Notifications;

use App\Models\Payment;
use App\Models\PaymentRefund;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Báo cho người trả tiền khi đơn được hoàn. Email luôn gửi (bằng chứng giao dịch), chuông theo cài đặt. */
class PaymentRefunded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Payment $payment, public readonly ?PaymentRefund $refund = null) {}

    private function amountLabel(): string
    {
        return number_format((float) ($this->refund->amount ?? $this->payment->amount), 0, ',', '.').'₫';
    }

    private function isFull(): bool
    {
        return $this->payment->isRefunded();
    }

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
            'title' => $this->isFull() ? 'Đơn hàng đã được hoàn tiền' : 'Đơn hàng được hoàn một phần',
            'message' => "Đã hoàn {$this->amountLabel()} cho gói {$p->package->name}.",
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
            ->line("TOÁN AI đã hoàn **{$this->amountLabel()}** cho đơn **{$p->order_code}** (gói {$p->package->name}) về ví MoMo của bạn.")
            ->line($this->isFull()
                ? 'Gói học tương ứng đã được thu hồi. Thời gian tiền về ví tuỳ thuộc MoMo, thường trong vài phút đến vài giờ.'
                : 'Đây là hoàn một phần — gói học của bạn vẫn được giữ nguyên. Thời gian tiền về ví tuỳ thuộc MoMo, thường trong vài phút đến vài giờ.')
            ->action('Xem giao dịch', route('payment.show', $p))
            ->line('Cần hỗ trợ thêm, hãy trả lời email này hoặc gửi yêu cầu trong ứng dụng.');
    }
}
