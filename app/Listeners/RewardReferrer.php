<?php

namespace App\Listeners;

use App\Events\PaymentCompleted;
use App\Events\PaymentFullyRefunded;
use App\Services\Referral\ReferralService;

/** Thưởng / thu lại thưởng giới thiệu theo kết quả thanh toán của người được giới thiệu. */
class RewardReferrer
{
    public function __construct(private readonly ReferralService $referrals) {}

    public function handlePaymentCompleted(PaymentCompleted $event): void
    {
        $this->referrals->onPaymentCompleted($event->payment);
    }

    public function handlePaymentFullyRefunded(PaymentFullyRefunded $event): void
    {
        $this->referrals->onPaymentRefunded($event->payment);
    }
}
