<?php

namespace Tests\Feature\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentSucceeded;
use App\Services\Payment\Gateways\MomoGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

class MomoPaymentTest extends SubscriptionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.gateway' => 'momo',
            'payment.momo.partner_code' => 'MOMOTEST',
            'payment.momo.access_key' => 'test-access',
            'payment.momo.secret_key' => 'test-secret',
            'payment.momo.endpoint' => 'https://test-payment.momo.vn',
        ]);

        Notification::fake();
    }

    private function fakeMomoCreate(): void
    {
        Http::fake([
            'test-payment.momo.vn/v2/gateway/api/create' => fn (HttpRequest $r) => Http::response([
                'partnerCode' => 'MOMOTEST', 'orderId' => $r['orderId'], 'requestId' => $r['requestId'],
                'amount' => (int) $r['amount'], 'resultCode' => 0, 'message' => 'Thành công.',
                'payUrl' => 'https://test-payment.momo.vn/pay/'.$r['orderId'],
            ]),
        ]);
    }

    private function checkout(User $payer, string $slug = 'pro-thang', array $extra = []): Payment
    {
        $this->fakeMomoCreate();

        $this->actingAs($payer)
            ->post(route('packages.pay', $slug), $extra)
            ->assertRedirect();

        return Payment::latest('id')->firstOrFail();
    }

    /** IPN hợp lệ theo đúng thuật toán ký của MoMo. */
    private function ipn(Payment $payment, array $overrides = [], bool $sign = true): array
    {
        $payload = array_merge([
            'partnerCode' => 'MOMOTEST',
            'orderId' => $payment->order_code,
            'requestId' => (string) $payment->gateway_request_id,
            'amount' => $payment->amountInt(),
            'orderInfo' => 'TOAN AI',
            'orderType' => 'momo_wallet',
            'transId' => 4088878653,
            'resultCode' => 0,
            'message' => 'Thành công.',
            'payType' => 'qr',
            'responseTime' => 1721720663942,
            'extraData' => '',
        ], $overrides);

        $payload['signature'] = $sign ? app(MomoGateway::class)->signNotification($payload) : 'chu-ky-gia';

        return $payload;
    }

    private function postIpn(array $payload): void
    {
        $this->postJson(route('api.payment.momo.ipn'), $payload)->assertNoContent();
    }

    // --- Checkout --------------------------------------------------------------------------

    public function test_checkout_creates_pending_order_with_db_price_and_signed_request(): void
    {
        $student = $this->makeStudent();

        $this->fakeMomoCreate();
        $response = $this->actingAs($student)->post(route('packages.pay', 'pro-thang'), [
            'amount' => 1000, 'price' => 1000, // client cố sửa giá — phải bị bỏ qua
        ]);

        $payment = Payment::firstOrFail();
        $response->assertRedirect('https://test-payment.momo.vn/pay/'.$payment->order_code);

        $this->assertSame('99000.00', $payment->amount);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame(Subscription::STATUS_PENDING, $payment->subscription->status);
        $this->assertSame($student->id, $payment->subscription->user_id);

        Http::assertSent(function (HttpRequest $r) {
            $raw = "accessKey=test-access&amount={$r['amount']}&extraData=&ipnUrl={$r['ipnUrl']}&orderId={$r['orderId']}"
                ."&orderInfo={$r['orderInfo']}&partnerCode=MOMOTEST&redirectUrl={$r['redirectUrl']}"
                ."&requestId={$r['requestId']}&requestType=captureWallet";

            return $r['amount'] === '99000'
                && $r['signature'] === hash_hmac('sha256', $raw, 'test-secret')
                && ! isset($r['accessKey']); // accessKey chỉ dùng để ký, không gửi đi
        });
    }

    public function test_clicking_pay_twice_reuses_the_open_order(): void
    {
        $student = $this->makeStudent();

        $this->fakeMomoCreate();
        $this->actingAs($student)->post(route('packages.pay', 'pro-thang'))->assertRedirect();
        $this->actingAs($student)->post(route('packages.pay', 'pro-thang'))->assertRedirect();

        $this->assertSame(1, Payment::count());
        $this->assertSame(1, Subscription::count());
        Http::assertSentCount(1);
    }

    public function test_gateway_error_marks_order_failed_and_shows_message(): void
    {
        $student = $this->makeStudent();
        Http::fake(['*' => Http::response(['resultCode' => 13, 'message' => 'Xác thực doanh nghiệp thất bại.'])]);

        $this->actingAs($student)
            ->from(route('packages.checkout', 'pro-thang'))
            ->post(route('packages.pay', 'pro-thang'))
            ->assertRedirect(route('packages.checkout', 'pro-thang'))
            ->assertSessionHas('error');

        $payment = Payment::firstOrFail();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertSame(Subscription::STATUS_CANCELLED, $payment->subscription->status);
    }

    public function test_parent_pays_for_linked_child_only(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent('Bé Na');
        $other = $this->makeStudent('Người lạ');
        $this->link($parent, $child);

        $payment = $this->checkout($parent, 'premium-thang', ['con' => $child->id]);
        $this->assertSame($parent->id, $payment->user_id);
        $this->assertSame($child->id, $payment->subscription->user_id);

        $this->actingAs($parent)->post(route('packages.pay', 'premium-thang'), ['con' => $other->id])->assertForbidden();
    }

    // --- IPN -----------------------------------------------------------------------------

    public function test_valid_ipn_activates_subscription_once_even_if_sent_twice(): void
    {
        $this->freezeSecond();
        $student = $this->makeStudent();
        $payment = $this->checkout($student);

        $this->postIpn($this->ipn($payment));
        $this->postIpn($this->ipn($payment));

        $payment->refresh();
        $sub = $payment->subscription;
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('4088878653', $payment->gateway_transaction_id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertTrue($sub->ends_at->equalTo(now()->addDays(30)));
        $this->assertSame('pro', $this->service()->tier($student));

        $this->assertSame(1, AuditLog::where('action', 'payment.paid')->count());
        $this->assertSame(['processed', 'duplicate'], PaymentWebhookLog::orderBy('id')->pluck('result')->all());
        Notification::assertSentToTimes($student, PaymentSucceeded::class, 1);
    }

    public function test_ipn_with_bad_signature_is_logged_and_ignored(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);

        $this->postIpn($this->ipn($payment, sign: false));

        // Ký đúng nhưng sửa số tiền sau khi ký cũng là chữ ký sai.
        $tampered = $this->ipn($payment);
        $tampered['resultCode'] = 0;
        $tampered['amount'] = 1000;
        $this->postIpn($tampered);

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('free', $this->service()->tier($student));
        $this->assertSame(2, PaymentWebhookLog::where('result', 'invalid_signature')->where('signature_valid', false)->count());
    }

    public function test_ipn_signed_for_another_partner_code_is_rejected(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->postIpn($this->ipn($payment, ['partnerCode' => 'KHAC']));

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_amount_mismatch_does_not_activate_and_flags_order(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);

        $this->postIpn($this->ipn($payment, ['amount' => 1000]));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertNotNull($payment->flag_reason);
        $this->assertSame(Subscription::STATUS_PENDING, $payment->subscription->status);
        $this->assertSame('amount_mismatch', PaymentWebhookLog::value('result'));
        Notification::assertNothingSent();
    }

    public function test_failed_payment_ipn_cancels_order_and_subscription(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);

        $this->postIpn($this->ipn($payment, ['resultCode' => 1006, 'transId' => '', 'message' => 'Người dùng từ chối.']));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_FAILED, $payment->status);
        $this->assertSame(Subscription::STATUS_CANCELLED, $payment->subscription->status);

        // IPN thành công đến sau (MoMo báo lại) — tiền đã trừ thì vẫn phải cấp gói.
        $this->postIpn($this->ipn($payment));
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertSame('pro', $this->service()->tier($student));
    }

    public function test_ipn_for_unknown_order_is_logged(): void
    {
        $payload = [
            'partnerCode' => 'MOMOTEST', 'orderId' => 'KHONGTONTAI', 'requestId' => 'x', 'amount' => 99000,
            'orderInfo' => '', 'orderType' => 'momo_wallet', 'transId' => 1, 'resultCode' => 0, 'message' => '',
            'payType' => 'qr', 'responseTime' => 1, 'extraData' => '',
        ];
        $payload['signature'] = app(MomoGateway::class)->signNotification($payload);

        $this->postIpn($payload);

        $this->assertSame('not_found', PaymentWebhookLog::value('result'));
    }

    public function test_ipn_endpoint_needs_no_csrf_or_auth(): void
    {
        $this->post(route('api.payment.momo.ipn'), ['orderId' => 'x'])->assertNoContent();
        $this->assertSame('invalid_signature', PaymentWebhookLog::value('result'));
    }

    // --- Return URL & trang kết quả ---------------------------------------------------------

    public function test_return_url_never_trusts_query_string(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);
        // Không có IPN; MoMo query cũng báo đang xử lý.
        Http::fake(['*/v2/gateway/api/query' => Http::response(['orderId' => $payment->order_code, 'resultCode' => 1000, 'amount' => 99000])]);

        $this->actingAs($student)
            ->get(route('payment.return', ['orderId' => $payment->order_code, 'resultCode' => 0, 'message' => 'Thành công']))
            ->assertRedirect(route('payment.show', $payment));

        $this->actingAs($student)->get(route('payment.show', $payment))
            ->assertOk()
            ->assertSee('Đang chờ xác nhận từ MoMo');

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertSame('free', $this->service()->tier($student));
    }

    public function test_result_page_reconciles_with_momo_query_when_ipn_is_missing(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);
        Http::fake(['*/v2/gateway/api/query' => Http::response([
            'partnerCode' => 'MOMOTEST', 'orderId' => $payment->order_code, 'amount' => 99000,
            'transId' => 555, 'resultCode' => 0, 'message' => 'Thành công.',
        ])]);

        $this->actingAs($student)->get(route('payment.show', $payment))
            ->assertOk()
            ->assertSee('Thanh toán thành công');

        $this->assertSame('pro', $this->service()->tier($student));
        $this->assertSame('momo-query', PaymentWebhookLog::value('provider'));
    }

    public function test_other_users_cannot_see_an_order(): void
    {
        $payment = $this->checkout($this->makeStudent());
        $other = $this->makeStudent('Khác');

        $this->actingAs($other)->get(route('payment.show', $payment))->assertNotFound();
        $this->actingAs($other)->get(route('payment.status', $payment))->assertNotFound();
        $this->actingAs($other)->get(route('payment.return', ['orderId' => $payment->order_code]))
            ->assertRedirect(route('payment.history'));
    }

    public function test_history_lists_only_own_payments(): void
    {
        $student = $this->makeStudent();
        $mine = $this->checkout($student);
        $theirs = $this->checkout($this->makeStudent('Khác'));

        $this->actingAs($student)->get(route('payment.history'))
            ->assertOk()
            ->assertSee($mine->order_code)
            ->assertDontSee($theirs->order_code);
    }

    // --- Hết hạn & quản trị ------------------------------------------------------------------

    public function test_stale_pending_orders_are_cancelled_after_checking_momo(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);
        Http::fake(['*/v2/gateway/api/query' => Http::response(['orderId' => $payment->order_code, 'resultCode' => 1005, 'amount' => 99000])]);

        $this->travel(31)->minutes();
        $this->artisan('payments:expire-pending')->assertSuccessful();

        $payment->refresh();
        $this->assertContains($payment->status, [Payment::STATUS_FAILED, Payment::STATUS_CANCELLED]);
        $this->assertSame(Subscription::STATUS_CANCELLED, $payment->subscription->status);
    }

    public function test_admin_sees_transactions_logs_and_can_reconcile(): void
    {
        $admin = $this->makeAdmin();
        $payment = $this->checkout($this->makeStudent());
        $this->postIpn($this->ipn($payment, ['amount' => 1]));

        $this->actingAs($admin)->get(route('admin.payments.index', ['status' => 'flagged']))
            ->assertOk()->assertSee($payment->order_code);

        $this->actingAs($admin)->get(route('admin.payments.show', $payment))
            ->assertOk()->assertSee('Cần xử lý tay')->assertSee('amount_mismatch');

        Http::fake(['*/v2/gateway/api/query' => Http::response([
            'orderId' => $payment->order_code, 'amount' => 99000, 'transId' => 9, 'resultCode' => 0, 'message' => 'OK',
        ])]);
        $this->actingAs($admin)->post(route('admin.payments.reconcile', $payment))->assertSessionHas('status');
        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);

        $this->actingAs($this->makeStudent('HS'))->get(route('admin.payments.index'))->assertForbidden();
    }

    // --- Giả lập local ---------------------------------------------------------------------

    public function test_fake_gateway_simulator_runs_the_real_ipn_flow(): void
    {
        config(['payment.gateway' => 'fake']);
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('packages.pay', 'pro-thang'))->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->assertSame(route('payment.simulator', $payment), $payment->pay_url);

        $this->actingAs($student)->get($payment->pay_url)->assertOk()->assertSee('Giả lập');
        $this->actingAs($student)->post(route('payment.simulate', $payment), ['result' => 'success'])
            ->assertRedirect();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertTrue(PaymentWebhookLog::where('signature_valid', true)->exists());
        Http::assertNothingSent();
    }

    public function test_simulator_is_unavailable_with_real_gateway(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);

        $this->actingAs($student)->get(route('payment.simulator', $payment))->assertNotFound();
        $this->actingAs($student)->post(route('payment.simulate', $payment), ['result' => 'success'])->assertNotFound();
    }
}
