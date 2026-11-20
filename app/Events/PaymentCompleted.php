<?php

namespace App\Events;

use App\Models\Payment;
use Illuminate\Foundation\Events\Dispatchable;

/** Đơn vừa được trả bằng tiền thật qua cổng (không gồm đơn 0đ của mã giảm 100%). Bắn SAU commit. */
class PaymentCompleted
{
    use Dispatchable;

    public function __construct(public readonly Payment $payment) {}
}
