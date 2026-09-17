<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use Illuminate\Support\Str;

/**
 * Giả lập MoMo cho local: không gọi mạng, payUrl trỏ về trang giả lập trong app.
 * Trang giả lập tạo IPN ký bằng đúng thuật toán MoMo rồi đưa qua PaymentService::handleNotification —
 * nên luồng verify chữ ký / so tiền / idempotency chạy thật khi dev.
 */
class FakeMomoGateway extends MomoGateway
{
    public function createPayment(Payment $payment, string $orderInfo): GatewayCheckout
    {
        return new GatewayCheckout(route('payment.simulator', $payment), (string) Str::uuid(), ['fake' => true]);
    }

    public function queryStatus(Payment $payment): ?GatewayNotification
    {
        return null;
    }

    protected function credentials(): array
    {
        return [
            'partner_code' => 'MOMOFAKE',
            'access_key' => 'fake-access-key',
            'secret_key' => 'fake-secret-key',
        ] + config('payment.momo');
    }
}
