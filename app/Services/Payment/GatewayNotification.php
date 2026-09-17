<?php

namespace App\Services\Payment;

final class GatewayNotification
{
    /** @param  array<string, mixed>  $raw */
    public function __construct(
        public readonly string $orderCode,
        public readonly int $amount,
        public readonly ?string $transactionId,
        public readonly int $resultCode,
        public readonly string $message,
        public readonly array $raw = [],
    ) {}

    public function isSuccess(): bool
    {
        return $this->resultCode === 0;
    }

    /**
     * Mã MoMo cho biết giao dịch CHƯA kết thúc (đang xử lý / chờ người dùng xác nhận) —
     * không được đánh dấu thất bại. 1000: chờ xác nhận, 7000/7002: đang xử lý.
     */
    public function isPending(): bool
    {
        return in_array($this->resultCode, [1000, 7000, 7002], true);
    }
}
