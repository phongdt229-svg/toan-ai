<?php

namespace App\Services\Payment;

/** Kết quả một yêu cầu hoàn tiền trả về từ cổng. */
final class GatewayRefund
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly bool $succeeded,
        public readonly ?string $transactionId,
        public readonly int $resultCode,
        public readonly string $message,
        public readonly array $raw = [],
    ) {}
}
