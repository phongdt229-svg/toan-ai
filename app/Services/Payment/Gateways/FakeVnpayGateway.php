<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\Contracts\SimulatesPayments;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use App\Services\Payment\GatewayRefund;
use Illuminate\Support\Str;

/** Giả lập VNPAY cho local — cùng khuôn với FakeMomoGateway: IPN giả ký đúng thuật toán VNPAY, đi qua luồng IPN thật. */
class FakeVnpayGateway extends VnpayGateway implements SimulatesPayments
{
    public function createPayment(Payment $payment, string $orderInfo): GatewayCheckout
    {
        return new GatewayCheckout(route('payment.simulator', $payment), (string) Str::uuid(), [
            'fake' => true,
            'vnp_CreateDate' => now(self::TZ)->format('YmdHis'),
        ]);
    }

    public function queryStatus(Payment $payment): ?GatewayNotification
    {
        return null;
    }

    public function refund(Payment $payment, string $refundCode, int $amount, string $reason): GatewayRefund
    {
        return new GatewayRefund(true, 'FAKE-RF-'.Str::upper(Str::random(8)), 0, 'Thành công (giả lập)', ['fake' => true]);
    }

    public function simulatedNotification(Payment $payment, bool $success): array
    {
        $payload = [
            'vnp_Amount' => (string) ($payment->amountInt() * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_OrderInfo' => 'Gia lap',
            'vnp_PayDate' => now(self::TZ)->format('YmdHis'),
            'vnp_ResponseCode' => $success ? '00' : '24',
            'vnp_TmnCode' => $this->tmnCode(),
            'vnp_TransactionNo' => $success ? (string) random_int(10_000_000, 99_999_999) : '0',
            'vnp_TransactionStatus' => $success ? '00' : '02',
            'vnp_TxnRef' => $payment->order_code,
        ];
        $payload['vnp_SecureHash'] = $this->signNotification($payload);

        return $payload;
    }

    protected function credentials(): array
    {
        return [
            'tmn_code' => 'VNPFAKE1',
            'hash_secret' => 'fake-vnpay-secret',
        ] + config('payment.vnpay');
    }
}
