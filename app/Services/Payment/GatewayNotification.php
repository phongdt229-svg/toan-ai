<?php

namespace App\Services\Payment;

final class GatewayNotification
{
    /**
     * `$resultCode` 0 = thành công với mọi cổng (cổng nào có mã riêng thì tự quy về 0 khi parse).
     * `$pending` null = suy theo mã MoMo; cổng khác tự khai đúng/sai.
     *
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public readonly string $orderCode,
        public readonly int $amount,
        public readonly ?string $transactionId,
        public readonly int $resultCode,
        public readonly string $message,
        public readonly array $raw = [],
        public readonly ?bool $pending = null,
    ) {}

    public function isSuccess(): bool
    {
        return $this->resultCode === 0;
    }

    /**
     * Giao dịch CHƯA kết thúc (đang xử lý / chờ người dùng xác nhận) — không được đánh dấu thất bại.
     * Mã MoMo: 1000 chờ xác nhận, 7000/7002 đang xử lý.
     */
    public function isPending(): bool
    {
        return $this->pending ?? in_array($this->resultCode, [1000, 7000, 7002], true);
    }
}
