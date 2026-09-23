<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use App\Services\Payment\GatewayRefund;
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

    /** Local: hoàn luôn thành công, không gọi mạng. Test muốn lỗi thì bind gateway khác. */
    public function refund(Payment $payment, string $refundCode, int $amount, string $reason): GatewayRefund
    {
        return new GatewayRefund(true, 'FAKE-RF-'.Str::upper(Str::random(8)), 0, 'Thành công (giả lập)', ['fake' => true]);
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
