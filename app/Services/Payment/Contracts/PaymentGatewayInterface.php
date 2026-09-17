<?php

namespace App\Services\Payment\Contracts;

use App\Models\Payment;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use App\Services\Payment\PaymentException;

interface PaymentGatewayInterface
{
    public function name(): string;

    /**
     * Tạo giao dịch bên cổng, trả link thanh toán.
     *
     * @throws PaymentException
     */
    public function createPayment(Payment $payment, string $orderInfo): GatewayCheckout;

    /** Chữ ký IPN có đúng không. Payload lấy nguyên văn từ request. */
    public function verifyNotification(array $payload): bool;

    /** Chuẩn hoá payload IPN đã verify. */
    public function parseNotification(array $payload): GatewayNotification;

    /**
     * Hỏi trạng thái giao dịch trực tiếp từ cổng (server → cổng qua HTTPS) — dùng khi IPN không tới được
     * (localhost, mạng lỗi). null = không hỏi được.
     */
    public function queryStatus(Payment $payment): ?GatewayNotification;
}
