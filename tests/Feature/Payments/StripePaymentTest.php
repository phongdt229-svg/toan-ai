<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\Gateways\StripeGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

/** Stripe Checkout (ST-01 → ST-05) — thu VND, webhook kiểm chữ ký trên thân request thô. */
class StripePaymentTest extends SubscriptionTestCase
{
    private const API = 'https://api.stripe.com/v1';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.gateway' => 'momo',
            'payment.methods' => ['momo', 'stripe'],
            'payment.stripe.secret_key' => 'sk_test_123',
            'payment.stripe.webhook_secret' => 'whsec_test',
        ]);

        Notification::fake();
    }

    private function fakeSession(): void
    {
        Http::fake([self::API.'/checkout/sessions' => fn (HttpRequest $r) => Http::response([
            'id' => 'cs_test_'.$r['client_reference_id'], 'url' => 'https://checkout.stripe.com/c/pay/cs_test_'.$r['client_reference_id'],
        ])]);
    }

    private function checkout(User $payer, string $slug = 'pro-thang'): Payment
    {
        $this->fakeSession();
        $this->actingAs($payer)->post(route('packages.pay', $slug), ['method' => 'stripe'])->assertRedirect();

        return Payment::latest('id')->firstOrFail();
    }

    /** @return array{0: string, 1: string} [thân request, header Stripe-Signature] */
    private function event(Payment $p, string $type = 'checkout.session.completed', array $object = [], ?int $t = null): array
    {
        $raw = json_encode(['id' => 'evt_1', 'type' => $type, 'data' => ['object' => array_merge([
            'id' => $p->gateway_request_id, 'object' => 'checkout.session', 'client_reference_id' => $p->order_code,
            'amount_total' => $p->amountInt(), 'currency' => 'vnd', 'payment_status' => 'paid', 'status' => 'complete',
            'payment_intent' => 'pi_123',
        ], $object)]]);
        $t ??= time();

        return [$raw, "t={$t},v1=".app(StripeGateway::class)->signWebhook($raw, $t)];
    }

    private function webhook(array $event)
    {
        [$raw, $sig] = $event;

        return $this->call('POST', route('api.payment.stripe.webhook'), [], [], [], [
            'CONTENT_TYPE' => 'application/json', 'HTTP_STRIPE_SIGNATURE' => $sig,
        ], $raw);
    }

    public function test_checkout_creates_a_session_with_db_price_in_vnd(): void
    {
        $student = $this->makeStudent();
        $this->fakeSession();

        $response = $this->actingAs($student)->post(route('packages.pay', 'pro-thang'), ['method' => 'stripe', 'amount' => 1000]);
        $payment = Payment::firstOrFail();

        $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_test_'.$payment->order_code);
        $this->assertSame(Payment::METHOD_STRIPE, $payment->method);
        $this->assertSame('cs_test_'.$payment->order_code, $payment->gateway_request_id);

        Http::assertSent(fn (HttpRequest $r) => $r['line_items[0][price_data][unit_amount]'] == 99000 // VND: không nhân 100
            && $r['line_items[0][price_data][currency]'] === 'vnd'
            && $r->hasHeader('Idempotency-Key', 'checkout-'.$payment->order_code)
            && $r->hasHeader('Authorization', 'Bearer sk_test_123'));
    }

    public function test_signed_completed_event_activates_the_package(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->webhook($this->event($payment))->assertOk();

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('pi_123', $payment->gateway_transaction_id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $payment->subscription->status);
        $this->assertSame('stripe', PaymentWebhookLog::latest('id')->value('provider'));
    }

    public function test_bad_or_stale_signatures_are_refused_with_400(): void
    {
        $payment = $this->checkout($this->makeStudent());

        [$raw] = $this->event($payment);
        $this->webhook([$raw, 't='.time().',v1=deadbeef'])->assertStatus(400);
        $this->webhook($this->event($payment, t: time() - 600))->assertStatus(400); // quá 5 phút → chống phát lại

        // Sửa thân request sau khi ký cũng không qua.
        [$raw, $sig] = $this->event($payment);
        $this->webhook([str_replace('"paid"', '"paid" ', $raw), $sig])->assertStatus(400);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_redelivered_event_is_idempotent(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->webhook($this->event($payment))->assertOk();
        $this->webhook($this->event($payment))->assertOk();

        $this->assertSame(1, Subscription::where('status', Subscription::STATUS_ACTIVE)->count());
        $this->assertSame(PaymentWebhookLog::RESULT_DUPLICATE, PaymentWebhookLog::latest('id')->value('result'));
    }

    public function test_wrong_currency_is_flagged_not_activated(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->webhook($this->event($payment, object: ['currency' => 'usd', 'amount_total' => 400]))->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->flag_reason);
    }

    public function test_expired_session_cancels_the_order(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->webhook($this->event($payment, 'checkout.session.expired', [
            'payment_status' => 'unpaid', 'status' => 'expired', 'payment_intent' => null,
        ]))->assertOk();

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame(Subscription::STATUS_CANCELLED, $payment->fresh()->subscription->status);
    }

    public function test_unrelated_events_are_acknowledged_and_ignored(): void
    {
        $raw = json_encode(['type' => 'customer.created', 'data' => ['object' => ['id' => 'cus_1']]]);
        $t = time();

        $this->webhook([$raw, "t={$t},v1=".app(StripeGateway::class)->signWebhook($raw, $t)])->assertOk();
        $this->assertSame(PaymentWebhookLog::RESULT_NOT_FOUND, PaymentWebhookLog::latest('id')->value('result'));
    }

    public function test_stripe_event_cannot_settle_a_momo_order(): void
    {
        config(['payment.momo.partner_code' => 'M', 'payment.momo.access_key' => 'a', 'payment.momo.secret_key' => 's']);
        Http::fake(['test-payment.momo.vn/*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://test-payment.momo.vn/pay/x'])]);
        $this->actingAs($this->makeStudent())->post(route('packages.pay', 'pro-thang'), ['method' => 'momo']);
        $momo = Payment::firstOrFail();

        $this->webhook($this->event($momo))->assertOk();

        $this->assertSame(Payment::STATUS_PENDING, $momo->fresh()->status);
    }

    public function test_reconcile_reads_the_session_when_webhook_never_arrives(): void
    {
        $payment = $this->checkout($this->makeStudent());
        Http::fake([self::API.'/checkout/sessions/*' => Http::response([
            'id' => $payment->gateway_request_id, 'client_reference_id' => $payment->order_code, 'amount_total' => 99000,
            'currency' => 'vnd', 'payment_status' => 'paid', 'status' => 'complete', 'payment_intent' => 'pi_9',
        ])]);

        $this->actingAs($this->makeAdmin())->post(route('admin.payments.reconcile', $payment))->assertSessionHas('status');

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
    }

    public function test_admin_refund_goes_through_stripe_with_idempotency(): void
    {
        $payment = $this->checkout($this->makeStudent());
        $this->webhook($this->event($payment));
        Http::fake([self::API.'/refunds' => Http::response(['id' => 're_1', 'status' => 'succeeded'])]);

        $this->actingAs($this->makeAdmin())->post(route('admin.payments.refund', $payment), ['reason' => 'Đặt nhầm'])
            ->assertSessionHas('status');

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/refunds')
            && $r['payment_intent'] === 'pi_123' && $r['amount'] == 99000
            && str_starts_with($r->header('Idempotency-Key')[0] ?? '', 'refund-RF'));
    }

    public function test_fake_stripe_runs_the_real_webhook_flow_locally(): void
    {
        config(['payment.gateway' => 'fake']);
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('packages.pay', 'pro-thang'), ['method' => 'stripe']);
        $payment = Payment::firstOrFail();
        $this->assertSame(route('payment.simulator', $payment), $payment->pay_url);

        $this->actingAs($student)->post(route('payment.simulate', $payment), ['result' => 'success'])->assertRedirect();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        Http::assertNothingSent();
    }

    public function test_check_command_and_page_disclosures(): void
    {
        Http::fake([self::API.'/balance' => Http::response(['available' => []])]);
        $this->artisan('payments:check-stripe')->expectsOutputToContain('secret key hợp lệ (test mode)')->assertSuccessful();

        config(['payment.stripe.secret_key' => null]);
        $this->artisan('payments:check-stripe')->expectsOutputToContain('Thiếu STRIPE_SECRET_KEY')->assertFailed();

        // Bật Stripe → Chính sách bảo mật khai Stripe; tắt → không nhắc.
        $this->get(route('legal.privacy'))->assertSee('Stripe, Inc.');
        config(['payment.methods' => ['momo']]);
        $this->get(route('legal.privacy'))->assertDontSee('Stripe, Inc.');

        $this->assertStringContainsString('https://checkout.stripe.com', $this->get('/')->headers->get('Content-Security-Policy'));
    }
}
