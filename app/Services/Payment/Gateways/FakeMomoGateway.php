<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\Contracts\SimulatesPayments;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use App\Services\Payment\GatewayRefund;
use Illuminate\Support\Str;

/**
 * Giả lập MoMo cho local: không gọi mạng, payUrl trỏ về trang giả lập trong app.
 * Trang giả lập tạo IPN ký bằng đúng thuật toán MoMo rồi đưa qua PaymentService::handleNotification —
 * nên luồng verify chữ ký / so tiền / idempotency chạy thật khi dev.
 */
class FakeMomoGateway extends MomoGateway implements SimulatesPayments
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

    public function simulatedNotification(Payment $payment, bool $success): array
    {
        $payload = [
            'partnerCode' => $this->partnerCode(),
            'orderId' => $payment->order_code,
            'requestId' => (string) $payment->gateway_request_id,
            'amount' => $payment->amountInt(),
            'orderInfo' => 'Gia lap',
            'orderType' => 'momo_wallet',
            'transId' => $success ? (string) random_int(1_000_000_000, 9_999_999_999) : '',
            'resultCode' => $success ? 0 : 1006,
            'message' => $success ? 'Thành công.' : 'Người dùng đã từ chối xác nhận thanh toán.',
            'payType' => 'qr',
            'responseTime' => now()->getTimestampMs(),
            'extraData' => '',
        ];
        $payload['signature'] = $this->signNotification($payload);

        return $payload;
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
