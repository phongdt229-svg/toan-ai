<?php

namespace App\Notifications;

use App\Models\Voucher;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Bạn được giới thiệu vừa mua gói → người giới thiệu nhận mã giảm giá riêng. */
class ReferralRewarded extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly Voucher $voucher) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? [] : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Bạn nhận được quà giới thiệu',
            'message' => 'Một bạn em giới thiệu vừa đăng ký gói học. Mã '.$this->voucher->code.' giảm '
                .(int) $this->voucher->value.'% cho lần gia hạn tới của em.',
            'url' => route('student.referrals.index'),
            'icon' => 'bi-gift',
        ];
    }
}
