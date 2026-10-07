<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\Gateways\VnpayGateway;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

/** Cổng VNPAY (đặc tả module 11) — chạy song song với MoMo, đơn nào đi đúng cổng đó. */
class VnpayPaymentTest extends SubscriptionTestCase
{
    private const SECRET = 'VNPAYTESTSECRET';

    private const API = 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.gateway' => 'momo',
            'payment.methods' => ['momo', 'vnpay'],
            'payment.vnpay.tmn_code' => 'VNPTEST1',
            'payment.vnpay.hash_secret' => self::SECRET,
            'payment.vnpay.pay_url' => 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html',
            'payment.vnpay.api_url' => self::API,
            'payment.momo.partner_code' => 'MOMOTEST',
            'payment.momo.access_key' => 'test-access',
            'payment.momo.secret_key' => 'test-secret',
        ]);

        Notification::fake();
    }

    private function checkout(User $payer, string $slug = 'pro-thang'): Payment
    {
        $this->actingAs($payer)->post(route('packages.pay', $slug), ['method' => 'vnpay'])->assertRedirect();

        return Payment::latest('id')->firstOrFail();
    }

    /** IPN đúng thuật toán ký VNPAY. */
    private function ipn(Payment $payment, array $overrides = [], bool $sign = true): array
    {
        $payload = array_merge([
            'vnp_Amount' => (string) ($payment->amountInt() * 100),
            'vnp_BankCode' => 'NCB',
            'vnp_OrderInfo' => 'TOAN AI',
            'vnp_PayDate' => '20261007120000',
            'vnp_ResponseCode' => '00',
            'vnp_TmnCode' => 'VNPTEST1',
            'vnp_TransactionNo' => '14512345',
            'vnp_TransactionStatus' => '00',
            'vnp_TxnRef' => $payment->order_code,
        ], $overrides);

        $payload['vnp_SecureHash'] = $sign ? app(VnpayGateway::class)->signNotification($payload) : str_repeat('a', 128);

        return $payload;
    }

    private function getIpn(array $payload): string
    {
        return $this->getJson(route('api.payment.vnpay.ipn', $payload))->assertOk()->json('RspCode');
    }

    // --- Tạo giao dịch ---------------------------------------------------------------------

    public function test_checkout_builds_signed_vnpay_url_with_db_price(): void
    {
        $student = $this->makeStudent();

        $response = $this->actingAs($student)->post(route('packages.pay', 'pro-thang'), ['method' => 'vnpay', 'amount' => 1000]);
        $payment = Payment::firstOrFail();

        $this->assertSame(Payment::METHOD_VNPAY, $payment->method);
        $this->assertStringStartsWith('https://sandbox.vnpayment.vn/paymentv2/vpcpay.html?', $payment->pay_url);
        $response->assertRedirect($payment->pay_url);

        parse_str((string) parse_url($payment->pay_url, PHP_URL_QUERY), $query);
        $this->assertSame((string) (99000 * 100), $query['vnp_Amount'], 'Giá lấy từ DB, nhân 100 theo chuẩn VNPAY.');
        $this->assertSame($payment->order_code, $query['vnp_TxnRef']);
        $this->assertSame(route('payment.return.vnpay'), $query['vnp_ReturnUrl']);

        // Chữ ký trên URL phải tự kiểm lại được bằng đúng thuật toán.
        $hash = $query['vnp_SecureHash'];
        unset($query['vnp_SecureHash']);
        ksort($query);
        $data = collect($query)->map(fn ($v, $k) => urlencode($k).'='.urlencode($v))->implode('&');
        $this->assertSame(hash_hmac('sha512', $data, self::SECRET), $hash);

        $this->assertNotEmpty($payment->gateway_response['create']['vnp_CreateDate']);
        Http::assertNothingSent();
    }

    public function test_disabled_method_is_rejected(): void
    {
        config(['payment.methods' => ['momo']]);
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('packages.pay', 'pro-thang'), ['method' => 'vnpay'])
            ->assertSessionHas('error');

        $this->assertSame(0, Payment::count());
    }

    public function test_checkout_page_shows_both_buttons_when_both_enabled(): void
    {
        $this->actingAs($this->makeStudent())->get(route('packages.checkout', 'pro-thang'))
            ->assertOk()
            ->assertSee('Thanh toán qua VNPAY')
            ->assertSee('Thanh toán bằng MoMo');
    }

    // --- IPN -------------------------------------------------------------------------------

    public function test_valid_ipn_activates_subscription(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->assertSame('00', $this->getIpn($this->ipn($payment)));

        $payment->refresh();
        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame('14512345', $payment->gateway_transaction_id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $payment->subscription->status);
        $this->assertSame('vnpay', PaymentWebhookLog::latest('id')->value('provider'));
    }

    public function test_repeated_ipn_is_acknowledged_as_already_confirmed(): void
    {
        $payment = $this->checkout($this->makeStudent());
        $this->getIpn($this->ipn($payment));

        $this->assertSame('02', $this->getIpn($this->ipn($payment)));
        $this->assertSame(1, Subscription::where('status', Subscription::STATUS_ACTIVE)->count());
    }

    public function test_bad_signature_is_refused(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->assertSame('97', $this->getIpn($this->ipn($payment, sign: false)));
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_tampered_amount_is_flagged_not_activated(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->assertSame('04', $this->getIpn($this->ipn($payment, ['vnp_Amount' => '100000'])));
        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
        $this->assertNotNull($payment->fresh()->flag_reason);
    }

    public function test_cancelled_by_customer_marks_order_failed(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->assertSame('00', $this->getIpn($this->ipn($payment, [
            'vnp_ResponseCode' => '24', 'vnp_TransactionStatus' => '02', 'vnp_TransactionNo' => '0',
        ])));

        $this->assertSame(Payment::STATUS_FAILED, $payment->fresh()->status);
        $this->assertSame(Subscription::STATUS_CANCELLED, $payment->fresh()->subscription->status);
    }

    public function test_vnpay_ipn_cannot_settle_a_momo_order(): void
    {
        Http::fake(['test-payment.momo.vn/*' => fn (HttpRequest $r) => Http::response([
            'resultCode' => 0, 'payUrl' => 'https://test-payment.momo.vn/pay/'.$r['orderId'],
        ])]);
        $student = $this->makeStudent();
        $this->actingAs($student)->post(route('packages.pay', 'pro-thang'), ['method' => 'momo']);
        $momoOrder = Payment::firstOrFail();

        $this->assertSame('01', $this->getIpn($this->ipn($momoOrder)));
        $this->assertSame(Payment::STATUS_PENDING, $momoOrder->fresh()->status);
    }

    public function test_return_url_only_redirects_to_order_page(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);

        // Tham số "thành công" trên URL trả về không được kích hoạt gì.
        $this->actingAs($student)
            ->get(route('payment.return.vnpay', ['vnp_TxnRef' => $payment->order_code, 'vnp_ResponseCode' => '00']))
            ->assertRedirect(route('payment.show', $payment));

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    // --- Truy vấn & hoàn tiền --------------------------------------------------------------

    /** Phản hồi merchant_webapi ký theo chuỗi `|` như tài liệu VNPAY. */
    private function apiResponse(array $fields, array $order): array
    {
        $fields['vnp_SecureHash'] = hash_hmac('sha512', implode('|', array_map(fn ($k) => (string) ($fields[$k] ?? ''), $order)), self::SECRET);

        return $fields;
    }

    public function test_reconcile_uses_querydr_when_ipn_never_arrives(): void
    {
        $payment = $this->checkout($this->makeStudent());

        Http::fake([self::API => fn (HttpRequest $r) => Http::response($this->apiResponse([
            'vnp_ResponseId' => 'r1', 'vnp_Command' => 'querydr', 'vnp_ResponseCode' => '00', 'vnp_Message' => 'OK',
            'vnp_TmnCode' => 'VNPTEST1', 'vnp_TxnRef' => $r['vnp_TxnRef'], 'vnp_Amount' => (string) ($payment->amountInt() * 100),
            'vnp_BankCode' => 'NCB', 'vnp_PayDate' => '20261007120000', 'vnp_TransactionNo' => '777',
            'vnp_TransactionType' => '01', 'vnp_TransactionStatus' => '00', 'vnp_OrderInfo' => 'x',
            'vnp_PromotionCode' => '', 'vnp_PromotionAmount' => '',
        ], ['vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message', 'vnp_TmnCode', 'vnp_TxnRef', 'vnp_Amount',
            'vnp_BankCode', 'vnp_PayDate', 'vnp_TransactionNo', 'vnp_TransactionType', 'vnp_TransactionStatus',
            'vnp_OrderInfo', 'vnp_PromotionCode', 'vnp_PromotionAmount']))]);

        $this->actingAs($this->makeAdmin())->post(route('admin.payments.reconcile', $payment))->assertSessionHas('status');

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        Http::assertSent(function (HttpRequest $r) use ($payment) {
            return $r['vnp_Command'] === 'querydr'
                && $r['vnp_TransactionDate'] === $payment->gateway_response['create']['vnp_CreateDate'];
        });
    }

    public function test_querydr_with_bad_signature_is_ignored(): void
    {
        $payment = $this->checkout($this->makeStudent());

        Http::fake([self::API => Http::response([
            'vnp_ResponseCode' => '00', 'vnp_TxnRef' => $payment->order_code, 'vnp_TransactionStatus' => '00',
            'vnp_Amount' => (string) ($payment->amountInt() * 100), 'vnp_SecureHash' => 'gia',
        ])]);

        $this->actingAs($this->makeAdmin())->post(route('admin.payments.reconcile', $payment));

        $this->assertSame(Payment::STATUS_PENDING, $payment->fresh()->status);
    }

    public function test_admin_refund_goes_through_vnpay(): void
    {
        $payment = $this->checkout($this->makeStudent());
        $this->getIpn($this->ipn($payment));

        Http::fake([self::API => fn (HttpRequest $r) => Http::response([
            'vnp_ResponseCode' => '00', 'vnp_Message' => 'Refund success', 'vnp_TransactionNo' => '888', 'vnp_TxnRef' => $r['vnp_TxnRef'],
        ])]);

        $this->actingAs($this->makeAdmin())->post(route('admin.payments.refund', $payment), ['reason' => 'Đặt nhầm gói'])
            ->assertSessionHas('status');

        $this->assertSame(Payment::STATUS_REFUNDED, $payment->fresh()->status);
        Http::assertSent(fn (HttpRequest $r) => $r['vnp_Command'] === 'refund'
            && $r['vnp_TransactionType'] === '02'
            && $r['vnp_Amount'] === 99000 * 100
            && $r['vnp_TransactionNo'] === '14512345');
    }

    // --- Giả lập local ---------------------------------------------------------------------

    public function test_fake_vnpay_simulator_runs_the_real_ipn_flow(): void
    {
        config(['payment.gateway' => 'fake']);
        $student = $this->makeStudent();

        $payment = $this->checkout($student);
        $this->assertSame(route('payment.simulator', $payment), $payment->pay_url);

        $this->actingAs($student)->get($payment->pay_url)->assertOk()->assertSee('VNPAY');
        $this->actingAs($student)->post(route('payment.simulate', $payment), ['result' => 'success'])->assertRedirect();

        $this->assertSame(Payment::STATUS_PAID, $payment->fresh()->status);
        $this->assertTrue(PaymentWebhookLog::where('provider', 'vnpay')->where('signature_valid', true)->exists());
        Http::assertNothingSent();
    }
}
