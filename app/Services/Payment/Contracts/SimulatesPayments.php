<?php

namespace App\Services\Payment\Contracts;

use App\Models\Payment;

/** Cổng giả lập ở local: tạo payload IPN ký đúng thuật toán của cổng thật để chạy qua luồng IPN thật. */
interface SimulatesPayments
{
    /** @return array<string, mixed> */
    public function simulatedNotification(Payment $payment, bool $success): array;
}
