<?php

namespace Tests\Feature\Payments;

use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Payment\PaymentService;
use App\Support\VietQr;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

/** Chuyển khoản QR (VietQR) — xác nhận qua webhook biến động số dư (SePay) hoặc quản trị xác nhận tay. */
class BankQrPaymentTest extends SubscriptionTestCase
{
    private const SECRET = 'sepay-test-secret-key';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.gateway' => 'momo',
            'payment.methods' => ['momo', 'bank_qr'],
            'payment.bank_qr.bank_bin' => '970416',
            'payment.bank_qr.bank_name' => 'ACB',
            'payment.bank_qr.account_no' => '244556559',
            'payment.bank_qr.account_name' => 'NGUYEN PHU DANH',
            'payment.bank_qr.webhook_secret' => self::SECRET,
            'payment.bank_qr.expires_minutes' => 15,
        ]);

        Notification::fake();
    }

    private function checkout(User $payer, string $slug = 'pro-thang'): Payment
    {
        $this->actingAs($payer)->post(route('packages.pay', $slug), ['method' => 'bank_qr'])
            ->assertRedirect();

        return Payment::latest('id')->firstOrFail();
    }

    /** Payload đúng định dạng webhook SePay. */
    private function webhook(Payment $payment, array $overrides = []): array
    {
        return array_merge([
            'id' => 92704,
            'gateway' => 'ACB',
            'transactionDate' => '2026-11-17 15:30:00',
            'accountNumber' => '244556559',
            'code' => null,
            'content' => 'MBVCB.123456 '.$payment->order_code.' CHUYEN TIEN',
            'transferType' => 'in',
            'transferAmount' => $payment->amountInt(),
            'accumulated' => 19077000,
            'referenceCode' => 'FT26321123456',
            'description' => '',
        ], $overrides);
    }

    private function postWebhook(array $payload, ?string $key = self::SECRET)
    {
        return $this->postJson(route('api.payment.bank-qr.webhook'), $payload, $key === null ? [] : ['Authorization' => 'Apikey '.$key]);
    }

    // --- Mã VietQR ---------------------------------------------------------------------------

    public function test_vietqr_payload_follows_emvco_with_valid_crc(): void
    {
        $payload = VietQr::payload('970416', '244556559', 699000, 'TOANAI261117154617ABCDEF');

        $this->assertStringStartsWith('000201010212', $payload);
        $this->assertStringContainsString('0010A000000727', $payload);
        $this->assertStringContainsString('0006970416', $payload);
        $this->assertStringContainsString('0109244556559', $payload);
        $this->assertStringContainsString('0208QRIBFTTA', $payload);
        $this->assertStringContainsString('5303704', $payload);
        $this->assertStringContainsString('5406699000', $payload);
        $this->assertStringContainsString('0824TOANAI261117154617ABCDEF', $payload);

        // CRC ở 4 ký tự cuối, tính trên phần còn lại (kể cả "6304").
        $this->assertSame(VietQr::crc16(substr($payload, 0, -4)), substr($payload, -4));
        // Giá trị chuẩn CRC-16/CCITT-FALSE của "123456789".
        $this->assertSame('29B1', VietQr::crc16('123456789'));
    }

    // --- Tạo đơn + trang QR ----------------------------------------------------------------------

    public function test_checkout_page_shows_bank_qr_button(): void
    {
        $this->actingAs($this->makeStudent())->get(route('packages.checkout', 'pro-thang'))
            ->assertOk()
            ->assertSee('value="bank_qr"', false)
            ->assertSee('Chuyển khoản QR');
    }

    public function test_checkout_creates_order_and_shows_qr_with_account_details(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);

        $this->assertSame(Payment::METHOD_BANK_QR, $payment->method);
        $this->assertSame(Payment::STATUS_PENDING, $payment->status);
        $this->assertSame((int) $this->package('pro-thang')->price, $payment->amountInt()); // giá từ DB
        $this->assertEqualsWithDelta(15, now()->diffInMinutes($payment->expires_at), 1); // hạn riêng của QR

        $this->actingAs($student)->get(route('payment.show', $payment))
            ->assertOk()
            ->assertSee('Quét mã để chuyển khoản')
            ->assertSee('<svg', false)
            ->assertSee('244556559')
            ->assertSee('NGUYEN PHU DANH')
            ->assertSee($payment->order_code)
            ->assertSee('data-poll-max', false)
            ->assertDontSee('Tiếp tục thanh toán');
    }

    public function test_unconfigured_account_refuses_bank_qr(): void
    {
        config(['payment.bank_qr.account_no' => '']);
        $student = $this->makeStudent();

        $this->actingAs($student)->post(route('packages.pay', 'pro-thang'), ['method' => 'bank_qr'])
            ->assertSessionHas('error');

        $this->assertSame(Payment::STATUS_FAILED, Payment::firstOrFail()->status);
    }

    // --- Webhook ---------------------------------------------------------------------------------

    public function test_webhook_with_matching_transfer_activates_subscription(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->postWebhook($this->webhook($payment))->assertOk()->assertJson(['success' => true]);

        $payment->refresh();
        $this->assertTrue($payment->isPaid());
        $this->assertSame('FT26321123456', $payment->gateway_transaction_id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $payment->subscription->status);

        // SePay gửi lại → không cấp gói lần hai.
        $this->postWebhook($this->webhook($payment))->assertOk();
        $this->assertSame(PaymentWebhookLog::RESULT_DUPLICATE, PaymentWebhookLog::latest('id')->first()->result);
    }

    public function test_order_code_is_found_even_in_lowercase_content(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->postWebhook($this->webhook($payment, ['content' => 'chuyen tien '.strtolower($payment->order_code)]))->assertOk();

        $this->assertTrue($payment->refresh()->isPaid());
    }

    public function test_wrong_or_missing_key_is_rejected_and_not_logged(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->postWebhook($this->webhook($payment), 'wrong-key')->assertStatus(401);
        $this->postWebhook($this->webhook($payment), null)->assertStatus(401);

        $this->assertTrue($payment->refresh()->isPending());
        // Khoá bí mật không bao giờ nằm trong log webhook mà quản trị xem được.
        foreach (PaymentWebhookLog::all() as $log) {
            $this->assertStringNotContainsString(self::SECRET, json_encode([$log->payload, $log->headers]));
        }
    }

    public function test_empty_secret_rejects_every_webhook(): void
    {
        config(['payment.bank_qr.webhook_secret' => '']);
        $payment = $this->checkout($this->makeStudent());

        $this->postWebhook($this->webhook($payment), '')->assertStatus(401);

        $this->assertTrue($payment->refresh()->isPending());
    }

    public function test_short_payment_is_flagged_and_listed_for_admin(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->postWebhook($this->webhook($payment, ['transferAmount' => 1000]))->assertOk();

        $payment->refresh();
        $this->assertTrue($payment->isPending());
        $this->assertNotNull($payment->flag_reason);

        $this->actingAs($this->makeAdmin())->get(route('admin.payments.index'))
            ->assertOk()
            ->assertViewHas('stats', fn ($stats) => $stats['unmatched_bank'] === 1)
            ->assertSee('Chuyển khoản chưa khớp đơn')
            ->assertSee('FT26321123456');
    }

    public function test_transfer_without_order_code_counts_as_unmatched(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->postWebhook($this->webhook($payment, ['content' => 'CHUYEN TIEN HOC PHI']))->assertOk();

        $this->assertTrue($payment->refresh()->isPending());
        $this->assertSame(PaymentWebhookLog::RESULT_NOT_FOUND, PaymentWebhookLog::latest('id')->first()->result);
        $this->actingAs($this->makeAdmin())->get(route('admin.payments.index'))
            ->assertViewHas('stats', fn ($stats) => $stats['unmatched_bank'] === 1);
    }

    public function test_outgoing_transfer_is_ignored(): void
    {
        $payment = $this->checkout($this->makeStudent());

        $this->postWebhook($this->webhook($payment, ['transferType' => 'out']))->assertOk();

        $this->assertTrue($payment->refresh()->isPending());
    }

    public function test_bank_qr_webhook_cannot_settle_a_momo_order(): void
    {
        config(['payment.momo.partner_code' => 'MOMOTEST', 'payment.momo.access_key' => 'a', 'payment.momo.secret_key' => 'b']);
        $student = $this->makeStudent();
        $momo = Payment::create([
            'order_code' => 'TOANAI261117000000MOMO01', 'user_id' => $student->id,
            'package_id' => $this->package('pro-thang')->id, 'amount' => 99000, 'currency' => 'VND',
            'method' => Payment::METHOD_MOMO, 'status' => Payment::STATUS_PENDING, 'expires_at' => now()->addMinutes(30),
        ]);

        $this->postWebhook($this->webhook($momo))->assertOk();

        $this->assertTrue($momo->refresh()->isPending());
    }

    // --- Quản trị xác nhận tay -------------------------------------------------------------------

    public function test_admin_confirms_transfer_manually_even_after_expiry(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);
        $payment->update(['expires_at' => now()->subMinute()]);
        app(PaymentService::class)->expireStale();
        $this->assertSame(Payment::STATUS_CANCELLED, $payment->refresh()->status);

        $admin = $this->makeAdmin();
        $this->actingAs($admin)->get(route('admin.payments.show', $payment))
            ->assertOk()->assertSee('Xác nhận đã nhận tiền');

        $this->actingAs($admin)->post(route('admin.payments.confirm-bank', $payment), ['reference' => 'FT26321999999'])
            ->assertSessionHas('status');

        $payment->refresh();
        $this->assertTrue($payment->isPaid());
        $this->assertSame('FT26321999999', $payment->gateway_transaction_id);
        $this->assertSame(Subscription::STATUS_ACTIVE, $payment->subscription->status);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.bank_transfer_confirmed']);
    }

    public function test_manual_confirmation_requires_reference_and_admin(): void
    {
        $student = $this->makeStudent();
        $payment = $this->checkout($student);

        $this->actingAs($this->makeAdmin())->post(route('admin.payments.confirm-bank', $payment), ['reference' => ''])
            ->assertSessionHasErrors('reference');
        $this->actingAs($student)->post(route('admin.payments.confirm-bank', $payment), ['reference' => 'FT1'])
            ->assertForbidden();

        $this->assertTrue($payment->refresh()->isPending());
    }

    public function test_manual_confirmation_is_refused_for_other_methods(): void
    {
        config(['payment.momo.partner_code' => 'MOMOTEST', 'payment.momo.access_key' => 'a', 'payment.momo.secret_key' => 'b']);
        $student = $this->makeStudent();
        $momo = Payment::create([
            'order_code' => 'TOANAI261117000000MOMO02', 'user_id' => $student->id,
            'package_id' => $this->package('pro-thang')->id, 'amount' => 99000, 'currency' => 'VND',
            'method' => Payment::METHOD_MOMO, 'status' => Payment::STATUS_PENDING, 'expires_at' => now()->addMinutes(30),
        ]);

        $this->actingAs($this->makeAdmin())->post(route('admin.payments.confirm-bank', $momo), ['reference' => 'FT1'])
            ->assertSessionHas('error');

        $this->assertTrue($momo->refresh()->isPending());
    }
}
