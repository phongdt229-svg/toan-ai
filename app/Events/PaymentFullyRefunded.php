<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/** Đơn vừa được hoàn đủ tiền (gói đã bị thu hồi). Bắn SAU commit. */
class PaymentFullyRefunded
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
