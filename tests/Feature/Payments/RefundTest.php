<?php

namespace Tests\Feature\Payments;

use App\Models\AuditLog;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\Subscription;
use App\Notifications\PaymentRefunded;
use App\Services\Payment\Gateways\MomoGateway;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

class RefundTest extends SubscriptionTestCase
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

    /** Đơn đã trả qua IPN thật, để có subscription active và transId. */
    private function paidPayment(): Payment
    {
        $student = $this->makeStudent();

        Http::fake(['test-payment.momo.vn/v2/gateway/api/create' => fn (HttpRequest $r) => Http::response([
            'resultCode' => 0, 'payUrl' => 'https://test-payment.momo.vn/pay/'.$r['orderId'],
        ])]);
        $this->actingAs($student)->post(route('packages.pay', 'pro-thang'))->assertRedirect();
        $payment = Payment::latest('id')->firstOrFail();

        $payload = [
            'partnerCode' => 'MOMOTEST', 'orderId' => $payment->order_code, 'requestId' => (string) $payment->gateway_request_id,
            'amount' => $payment->amountInt(), 'orderInfo' => 'TOAN AI', 'orderType' => 'momo_wallet', 'transId' => 4088878653,
            'resultCode' => 0, 'message' => 'Thành công.', 'payType' => 'qr', 'responseTime' => 1721720663942, 'extraData' => '',
        ];
        $payload['signature'] = app(MomoGateway::class)->signNotification($payload);
        $this->postJson(route('api.payment.momo.ipn'), $payload)->assertNoContent();

        return $payment->refresh();
    }

    /** Http::fake gọi lần hai không đè được stub cũ (stub khai trước thắng) — nên kết quả đọc từ tham chiếu để đổi giữa chừng. */
    private int $refundResult = 0;

    private function fakeRefund(int $resultCode = 0): void
    {
        $this->refundResult = $resultCode;

        Http::fake(['test-payment.momo.vn/v2/gateway/api/refund' => fn (HttpRequest $r) => Http::response([
            'partnerCode' => 'MOMOTEST', 'orderId' => $r['orderId'], 'requestId' => $r['requestId'], 'amount' => (int) $r['amount'],
            'transId' => 9900110022, 'resultCode' => $this->refundResult, 'message' => $this->refundResult === 0 ? 'Thành công.' : 'Giao dịch không đủ điều kiện hoàn.',
        ])]);
    }

    public function test_admin_refund_marks_order_refunded_cancels_subscription_and_sends_signed_request(): void
    {
        $payment = $this->paidPayment();
        $admin = $this->makeAdmin();
        $this->fakeRefund();

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), ['reason' => 'Khách đặt nhầm gói'])
            ->assertSessionHas('status');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertSame(Subscription::STATUS_CANCELLED, $payment->subscription->status);

        $refund = $payment->refunds()->firstOrFail();
        $this->assertSame(PaymentRefund::STATUS_SUCCEEDED, $refund->status);
        $this->assertSame('99000.00', $refund->amount);
        $this->assertSame($admin->id, $refund->requested_by);
        $this->assertTrue(AuditLog::where('action', 'payment.refunded')->exists());
        Notification::assertSentTo($payment->user, PaymentRefunded::class);

        Http::assertSent(function (HttpRequest $r) use ($payment) {
            if (! str_ends_with($r->url(), '/v2/gateway/api/refund')) {
                return false;
            }

            $raw = "accessKey=test-access&amount=99000&description={$r['description']}&orderId={$r['orderId']}"
                ."&partnerCode=MOMOTEST&requestId={$r['requestId']}&transId=4088878653";

            return $r['signature'] === hash_hmac('sha256', $raw, 'test-secret')
                && $r['orderId'] !== $payment->order_code  // mã lần hoàn khác mã đơn gốc
                && $r['transId'] === '4088878653'
                && ! isset($r['accessKey']);
        });
    }

    public function test_replayed_ipn_after_refund_does_not_reactivate_the_subscription(): void
    {
        $payment = $this->paidPayment();
        $this->fakeRefund();
        $this->actingAs($this->makeAdmin())->post(route('admin.payments.refund', $payment), ['reason' => 'x']);

        $payload = [
            'partnerCode' => 'MOMOTEST', 'orderId' => $payment->order_code, 'requestId' => (string) $payment->gateway_request_id,
            'amount' => $payment->amountInt(), 'orderInfo' => 'TOAN AI', 'orderType' => 'momo_wallet', 'transId' => 4088878653,
            'resultCode' => 0, 'message' => 'Thành công.', 'payType' => 'qr', 'responseTime' => 1721720663942, 'extraData' => '',
        ];
        $payload['signature'] = app(MomoGateway::class)->signNotification($payload);
        $this->postJson(route('api.payment.momo.ipn'), $payload)->assertNoContent();

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->refresh()->status);
        $this->assertSame(Subscription::STATUS_CANCELLED, $payment->subscription->status);
    }

    public function test_refunded_order_leaves_revenue(): void
    {
        $payment = $this->paidPayment();
        $this->assertSame(1, Payment::where('status', 'paid')->count());

        $this->fakeRefund();
        $this->actingAs($this->makeAdmin())->post(route('admin.payments.refund', $payment), ['reason' => 'x']);

        $this->assertSame(0, Payment::where('status', 'paid')->count());
    }

    public function test_gateway_refusal_keeps_the_order_paid_and_allows_retry(): void
    {
        $payment = $this->paidPayment();
        $admin = $this->makeAdmin();
        $this->fakeRefund(resultCode: 1080);

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), ['reason' => 'x'])->assertSessionHas('error');

        $this->assertSame(Payment::STATUS_PAID, $payment->refresh()->status);
        $this->assertSame(Subscription::STATUS_ACTIVE, $payment->subscription->status);
        $this->assertSame(PaymentRefund::STATUS_FAILED, $payment->refunds()->first()->status);
        Notification::assertNotSentTo($payment->user, PaymentRefunded::class);

        // Lần bị từ chối không chặn lần thử sau.
        $this->refundResult = 0;
        $this->post(route('admin.payments.refund', $payment), ['reason' => 'thử lại'])->assertSessionHas('status');
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->refresh()->status);
    }

    public function test_network_failure_leaves_pending_refund_that_blocks_a_second_attempt(): void
    {
        $payment = $this->paidPayment();
        $admin = $this->makeAdmin();

        Http::fake(['test-payment.momo.vn/v2/gateway/api/refund' => fn () => throw new ConnectionException('timeout')]);
        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), ['reason' => 'x'])->assertSessionHas('error');

        $this->assertSame(Payment::STATUS_PAID, $payment->refresh()->status);
        $this->assertSame(PaymentRefund::STATUS_PENDING, $payment->refunds()->first()->status);

        // Chưa rõ MoMo đã hoàn hay chưa → không được gửi lần hai.
        $this->fakeRefund();
        $this->post(route('admin.payments.refund', $payment), ['reason' => 'x'])->assertSessionHas('error');
        Http::assertNotSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/refund'));
        $this->assertSame(1, $payment->refunds()->count());
    }

    public function test_only_paid_non_free_orders_can_be_refunded(): void
    {
        $payment = $this->paidPayment();
        $admin = $this->makeAdmin();

        $payment->update(['status' => Payment::STATUS_FAILED]);
        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), ['reason' => 'x'])->assertSessionHas('error');

        $payment->update(['status' => Payment::STATUS_PAID, 'method' => Payment::METHOD_VOUCHER]);
        $this->post(route('admin.payments.refund', $payment), ['reason' => 'x'])->assertSessionHas('error');

        $this->assertSame(0, PaymentRefund::count());
    }

    public function test_reason_is_required_and_only_admin_can_refund(): void
    {
        $payment = $this->paidPayment();

        $this->actingAs($this->makeAdmin())->post(route('admin.payments.refund', $payment), [])->assertSessionHasErrors('reason');
        $this->actingAs($this->makeStudent())->post(route('admin.payments.refund', $payment), ['reason' => 'x'])->assertForbidden();
        $this->assertSame(Payment::STATUS_PAID, $payment->refresh()->status);
    }

    public function test_admin_payment_page_shows_refund_form_only_when_refundable(): void
    {
        $payment = $this->paidPayment();
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.payments.show', $payment))->assertOk()->assertSee('Hoàn tiền');

        $this->fakeRefund();
        $this->post(route('admin.payments.refund', $payment), ['reason' => 'x']);

        $this->get(route('admin.payments.show', $payment))->assertOk()
            ->assertSee('Đã hoàn tiền')
            ->assertDontSee(route('admin.payments.refund', $payment), false);
        $this->get(route('admin.payments.index'))->assertOk();
    }

    public function test_partial_refund_keeps_order_paid_and_subscription_active_and_nets_revenue(): void
    {
        $payment = $this->paidPayment();
        $admin = $this->makeAdmin();
        $this->fakeRefund();

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), ['reason' => 'Bồi hoàn sự cố', 'amount' => 30000])
            ->assertSessionHas('status');

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(Subscription::STATUS_ACTIVE, $payment->subscription->status);
        $this->assertSame('30000.00', $payment->refunded_amount);
        $this->assertSame(69000, $payment->refundableInt());
        $this->assertSame(69000, $payment->netAmountInt());

        // Doanh thu ròng trên trang Giao dịch: 99.000 − 30.000.
        $this->get(route('admin.payments.index'))->assertOk()->assertSee('69.000₫');

        Http::assertSent(fn (HttpRequest $r) => str_ends_with($r->url(), '/refund') && $r['amount'] === '30000');
        Notification::assertSentTo($payment->user, PaymentRefunded::class,
            fn (PaymentRefunded $n) => $n->toArray($payment->user)['title'] === 'Đơn hàng được hoàn một phần');
    }

    public function test_second_partial_refund_completing_the_amount_revokes_the_subscription(): void
    {
        $payment = $this->paidPayment();
        $admin = $this->makeAdmin();
        $this->fakeRefund();

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), ['reason' => 'a', 'amount' => 30000]);
        $this->post(route('admin.payments.refund', $payment), ['reason' => 'b']); // để trống = phần còn lại

        $payment->refresh();
        $this->assertSame(Payment::STATUS_REFUNDED, $payment->status);
        $this->assertSame('99000.00', $payment->refunded_amount);
        $this->assertSame(0, $payment->refundableInt());
        $this->assertSame(Subscription::STATUS_CANCELLED, $payment->subscription->status);
        $this->assertSame([30000, 69000], $payment->refunds()->orderBy('id')->pluck('amount')->map(fn ($a) => (int) $a)->all());
    }

    public function test_cannot_refund_more_than_what_is_left(): void
    {
        $payment = $this->paidPayment();
        $admin = $this->makeAdmin();
        $this->fakeRefund();

        $this->actingAs($admin)->post(route('admin.payments.refund', $payment), ['reason' => 'a', 'amount' => 60000]);

        $this->post(route('admin.payments.refund', $payment), ['reason' => 'b', 'amount' => 50000])->assertSessionHas('error');
        $this->post(route('admin.payments.refund', $payment), ['reason' => 'b', 'amount' => 0])->assertSessionHasErrors('amount');

        $this->assertSame('60000.00', $payment->refresh()->refunded_amount);
        $this->assertSame(1, $payment->refunds()->count());
    }
}
