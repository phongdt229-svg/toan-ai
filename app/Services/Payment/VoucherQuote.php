<?php

namespace App\Services\Payment;

use App\Models\Voucher;

/** Kết quả tính một mã trên một gói: giảm bao nhiêu, còn phải trả bao nhiêu. */
final class VoucherQuote
{
    public function __construct(
        public readonly Voucher $voucher,
        public readonly float $discount,
        public readonly float $payable,
    ) {}

    /** Gói được miễn phí hoàn toàn → không đi qua cổng thanh toán. */
    public function isFree(): bool
    {
        return $this->payable <= 0;
    }
}
