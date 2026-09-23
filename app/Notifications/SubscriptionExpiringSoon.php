<?php

namespace App\Notifications;

use App\Models\Subscription;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Gói sắp hết hạn — gửi ở mốc 7 / 3 / 1 ngày.
 *
 * Qua queue: chạy trong lệnh định kỳ, không có ai ngồi chờ. Người dùng tắt được loại này
 * trong Cài đặt thông báo (NotificationType).
 */
class SubscriptionExpiringSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Subscription $subscription,
        public readonly int $daysLeft,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? ['mail'] : ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $s = $this->subscription;
        $forSelf = $s->user_id === $notifiable->id;
        $who = $forSelf ? 'Gói học của bạn' : "Gói học của {$s->user->name}";

        return (new MailMessage)
            ->subject($this->daysLeft === 1
                ? 'Gói học hết hạn vào ngày mai'
                : "Gói học còn {$this->daysLeft} ngày")
            ->greeting("Chào {$notifiable->name},")
            ->line("{$who} — **{$s->package->name}** — hết hạn ngày **{$s->ends_at->format('d/m/Y')}**"
                .($this->daysLeft === 1 ? ', tức ngày mai.' : ", còn {$this->daysLeft} ngày."))
            ->line('Gia hạn trước ngày đó thì thời gian mới được **cộng nối tiếp**, không mất ngày nào.')
            ->action('Gia hạn ngay', route('packages.index'))
            ->line('Hết hạn mà chưa gia hạn thì tài khoản trở về gói Free — dữ liệu học tập vẫn giữ nguyên.')
            ->salutation('Thân mến, đội ngũ TOÁN AI');
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->daysLeft === 1 ? 'Gói học hết hạn vào ngày mai' : "Gói học còn {$this->daysLeft} ngày",
            'message' => "{$this->subscription->package->name} hết hạn ngày {$this->subscription->ends_at->format('d/m/Y')}.",
            'url' => route('packages.index'),
            'icon' => 'bi-hourglass-split',
        ];
    }
}
