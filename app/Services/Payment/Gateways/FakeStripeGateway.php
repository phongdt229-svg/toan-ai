<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\Contracts\SimulatesPayments;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use App\Services\Payment\GatewayRefund;
use Illuminate\Support\Str;

/** Giả lập Stripe cho local — webhook giả ký đúng chuẩn Stripe v1, đi qua luồng webhook thật. */
class FakeStripeGateway extends StripeGateway implements SimulatesPayments
{
    public function createPayment(Payment $payment, string $orderInfo): GatewayCheckout
    {
        $id = 'cs_test_fake_'.Str::lower(Str::random(16));

        return new GatewayCheckout(route('payment.simulator', $payment), $id, ['session_id' => $id, 'fake' => true]);
    }

    public function queryStatus(Payment $payment): ?GatewayNotification
    {
        return null;
    }

    public function refund(Payment $payment, string $refundCode, int $amount, string $reason): GatewayRefund
    {
        return new GatewayRefund(true, 're_fake_'.Str::lower(Str::random(10)), 0, 'succeeded', ['fake' => true]);
    }

    public function simulatedNotification(Payment $payment, bool $success): array
    {
        $raw = json_encode([
            'id' => 'evt_fake_'.Str::lower(Str::random(12)),
            'type' => $success ? 'checkout.session.completed' : 'checkout.session.expired',
            'data' => ['object' => [
                'id' => (string) $payment->gateway_request_id,
                'object' => 'checkout.session',
                'client_reference_id' => $payment->order_code,
                'metadata' => ['order_code' => $payment->order_code],
                'amount_total' => $payment->amountInt(),
                'currency' => 'vnd',
                'payment_status' => $success ? 'paid' : 'unpaid',
                'status' => $success ? 'complete' : 'expired',
                'payment_intent' => $success ? 'pi_fake_'.Str::lower(Str::random(12)) : null,
            ]],
        ], JSON_UNESCAPED_UNICODE);
        $t = time();

        return ['raw' => $raw, 'signature' => "t={$t},v1=".$this->signWebhook($raw, $t)];
    }

    protected function credentials(): array
    {
        return ['secret_key' => 'sk_test_fake', 'webhook_secret' => 'whsec_fake'] + config('payment.stripe');
    }
}
