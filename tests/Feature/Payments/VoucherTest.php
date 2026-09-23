<?php

namespace Tests\Feature\Payments;

use App\Models\Package;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentService;
use App\Services\Payment\VoucherException;
use App\Services\Payment\VoucherService;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

/**
 * Mã giảm giá (§8b).
 *
 * Điều phải giữ bằng mọi giá: số tiền do server tính, client chỉ gửi chuỗi mã.
 */
class VoucherTest extends SubscriptionTestCase
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

        Http::fake([
            'test-payment.momo.vn/v2/gateway/api/create' => fn (HttpRequest $r) => Http::response([
                'partnerCode' => 'MOMOTEST', 'orderId' => $r['orderId'], 'requestId' => $r['requestId'],
                'amount' => (int) $r['amount'], 'resultCode' => 0, 'message' => 'Thành công.',
                'payUrl' => 'https://test-payment.momo.vn/pay/'.$r['orderId'],
            ]),
        ]);
    }

    private function voucher(array $attributes = []): Voucher
    {
        return Voucher::create(array_merge([
            'code' => 'GIAM50',
            'type' => Voucher::TYPE_PERCENT,
            'value' => 50,
            'max_uses_per_user' => 1,
            'is_active' => true,
        ], $attributes));
    }

    private function pay(User $payer, Package $package): Payment
    {
        $this->actingAs($payer)->post(route('packages.pay', $package))->assertRedirect();

        return Payment::latest('id')->firstOrFail();
    }

    // --- Tính tiền ------------------------------------------------------------------------

    public function test_percent_voucher_reduces_the_amount_sent_to_the_gateway(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');
        $this->voucher();

        $this->actingAs($student)
            ->post(route('packages.voucher.apply', $package), ['code' => 'giam50'])
            ->assertSessionHasNoErrors();

        $payment = $this->pay($student, $package);

        $expected = (float) $package->price / 2;
        $this->assertEquals($expected, (float) $payment->amount);
        $this->assertEquals($expected, (float) $payment->discount_amount);
        $this->assertSame((float) $package->price, $payment->originalAmount());

        // Số tiền gửi sang MoMo phải là số đã giảm, không phải giá gốc.
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://test-payment.momo.vn/v2/gateway/api/create'
            && (int) $r['amount'] === (int) $expected);
    }

    public function test_fixed_voucher_and_percent_cap_are_applied(): void
    {
        $package = $this->package('pro-thang');
        $price = (float) $package->price;

        $fixed = $this->voucher(['code' => 'BOT20K', 'type' => Voucher::TYPE_FIXED, 'value' => 20000]);
        $this->assertSame(20000.0, $fixed->discountOn($price));

        $capped = $this->voucher(['code' => 'NUA100K', 'type' => Voucher::TYPE_PERCENT, 'value' => 50, 'max_discount' => 10000]);
        $this->assertSame(10000.0, $capped->discountOn($price));

        // Mã giảm tiền lớn hơn giá gói không bao giờ tạo ra số âm.
        $huge = $this->voucher(['code' => 'BOT9TRIEU', 'type' => Voucher::TYPE_FIXED, 'value' => 9000000]);
        $this->assertSame($price, $huge->discountOn($price));
    }

    // --- Điều kiện ------------------------------------------------------------------------

    public function test_expired_inactive_and_future_codes_are_refused(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');
        $vouchers = app(VoucherService::class);

        $this->voucher(['code' => 'HETHAN', 'ends_at' => now()->subDay()]);
        $this->voucher(['code' => 'CHUATOI', 'starts_at' => now()->addDay()]);
        $this->voucher(['code' => 'DATAT', 'is_active' => false]);

        foreach (['HETHAN' => 'hết hạn', 'CHUATOI' => 'chưa tới ngày', 'DATAT' => 'ngừng sử dụng'] as $code => $reason) {
            try {
                $vouchers->quote($code, $package, $student);
                $this->fail("Mã {$code} lẽ ra phải bị từ chối.");
            } catch (VoucherException $e) {
                $this->assertStringContainsString($reason, $e->getMessage());
            }
        }

        $this->expectException(VoucherException::class);
        $vouchers->quote('KHONGCO', $package, $student);
    }

    public function test_voucher_limited_to_another_package_does_not_apply(): void
    {
        $student = $this->makeStudent();
        $pro = $this->package('pro-thang');
        $premium = Package::where('slug', '!=', 'pro-thang')->where('price', '>', 0)->firstOrFail();

        $voucher = $this->voucher(['code' => 'CHIPREMIUM']);
        $voucher->packages()->sync([$premium->id]);

        $this->expectException(VoucherException::class);
        app(VoucherService::class)->quote('CHIPREMIUM', $pro, $student);
    }

    public function test_minimum_order_amount_is_enforced(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');
        $this->voucher(['code' => 'DONTO', 'min_order_amount' => (float) $package->price + 1]);

        $this->expectException(VoucherException::class);
        app(VoucherService::class)->quote('DONTO', $package, $student);
    }

    public function test_a_code_that_leaves_less_than_the_gateway_minimum_is_refused(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');
        // Còn lại đúng 500đ — trên 0 nhưng dưới mức MoMo nhận.
        $this->voucher(['code' => 'SATNUT', 'type' => Voucher::TYPE_FIXED, 'value' => (float) $package->price - 500]);

        $this->expectException(VoucherException::class);
        app(VoucherService::class)->quote('SATNUT', $package, $student);
    }

    // --- Giới hạn lượt --------------------------------------------------------------------

    public function test_per_user_limit_blocks_a_second_use(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');
        $this->voucher(['code' => 'MOINGUOI1']);

        $this->actingAs($student)->post(route('packages.voucher.apply', $package), ['code' => 'MOINGUOI1']);
        $first = $this->pay($student, $package);
        $this->assertEquals((float) $package->price / 2, (float) $first->amount);

        // Lượt đã bị giữ ngay khi tạo đơn, chưa cần trả tiền xong.
        $this->actingAs($student)
            ->post(route('packages.voucher.apply', $package), ['code' => 'MOINGUOI1'])
            ->assertSessionHasErrors('code');
    }

    public function test_total_uses_limit_is_enforced_across_users(): void
    {
        $package = $this->package('pro-thang');
        $this->voucher(['code' => 'CHI1LUOT', 'max_uses' => 1]);

        $first = $this->makeStudent('Người thứ nhất');
        $this->actingAs($first)->post(route('packages.voucher.apply', $package), ['code' => 'CHI1LUOT']);
        $this->pay($first, $package);

        $second = $this->makeStudent('Người thứ hai');
        $this->actingAs($second)
            ->post(route('packages.voucher.apply', $package), ['code' => 'CHI1LUOT'])
            ->assertSessionHasErrors('code');
    }

    public function test_a_cancelled_order_gives_the_use_back(): void
    {
        $package = $this->package('pro-thang');
        $this->voucher(['code' => 'TRALAI', 'max_uses' => 1]);

        $first = $this->makeStudent('Người bỏ dở');
        $this->actingAs($first)->post(route('packages.voucher.apply', $package), ['code' => 'TRALAI']);
        $payment = $this->pay($first, $package);

        // Đơn hết hạn → huỷ → lượt phải quay về kho.
        $payment->update(['expires_at' => now()->subMinute()]);
        Http::fake(['*' => Http::response(['resultCode' => 1005, 'message' => 'Hết hạn'], 200)]);
        app(PaymentService::class)->expireStale();

        $this->assertNotNull(VoucherRedemption::where('payment_id', $payment->id)->firstOrFail()->released_at);

        $second = $this->makeStudent('Người đến sau');
        $this->actingAs($second)
            ->post(route('packages.voucher.apply', $package), ['code' => 'TRALAI'])
            ->assertSessionHasNoErrors();
    }

    // --- Mã giảm 100% ---------------------------------------------------------------------

    public function test_a_hundred_percent_code_activates_without_calling_the_gateway(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');
        $this->voucher(['code' => 'MIENPHI', 'value' => 100]);

        $this->actingAs($student)->post(route('packages.voucher.apply', $package), ['code' => 'MIENPHI']);
        $payment = $this->pay($student, $package);

        $this->assertSame(Payment::STATUS_PAID, $payment->status);
        $this->assertSame(Payment::METHOD_VOUCHER, $payment->method);
        $this->assertEquals(0, (float) $payment->amount);
        $this->assertNull($payment->pay_url);

        // Gói phải được kích hoạt thật, và không có lời gọi nào sang MoMo.
        $this->assertSame(Subscription::STATUS_ACTIVE, $payment->subscription->refresh()->status);
        Http::assertNothingSent();

        $this->assertNotNull(VoucherRedemption::where('payment_id', $payment->id)->firstOrFail()->redeemed_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'payment.paid']);
    }

    // --- Không tin client -----------------------------------------------------------------

    public function test_the_client_cannot_send_its_own_amount_or_discount(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');

        $this->actingAs($student)
            ->post(route('packages.pay', $package), ['amount' => 1000, 'discount_amount' => 500000])
            ->assertRedirect();

        $payment = Payment::latest('id')->firstOrFail();
        $this->assertEquals((float) $package->price, (float) $payment->amount);
        $this->assertEquals(0, (float) $payment->discount_amount);
    }

    public function test_removing_the_code_brings_the_full_price_back(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');
        $this->voucher();

        $this->actingAs($student)->post(route('packages.voucher.apply', $package), ['code' => 'GIAM50']);
        $this->actingAs($student)->delete(route('packages.voucher.remove', $package))->assertSessionHas('status');

        $payment = $this->pay($student, $package);

        // Không được dùng lại đơn đã giảm của lượt trước.
        $this->assertEquals((float) $package->price, (float) $payment->amount);
        $this->assertNull($payment->voucher_id);
    }

    public function test_a_code_that_expires_between_screens_cannot_create_a_cheap_order(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');
        $voucher = $this->voucher(['code' => 'SAPHET']);

        $this->actingAs($student)->post(route('packages.voucher.apply', $package), ['code' => 'SAPHET']);

        // Admin tắt mã ngay trước khi người dùng bấm thanh toán.
        $voucher->update(['is_active' => false]);

        $this->actingAs($student)->post(route('packages.pay', $package))->assertSessionHas('error');
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_service_refuses_an_unknown_code_at_checkout(): void
    {
        $student = $this->makeStudent();

        $this->expectException(PaymentException::class);
        app(PaymentService::class)->checkout($student, $student, $this->package('pro-thang'), null, 'KHONGTONTAI');
    }

    // --- Quản trị -------------------------------------------------------------------------

    public function test_admin_can_create_a_voucher_and_it_is_audited(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.vouchers.store'), [
            'code' => 'khaigiang',
            'type' => Voucher::TYPE_PERCENT,
            'value' => 30,
            'max_uses_per_user' => 2,
            'is_active' => '1',
        ])->assertRedirect(route('admin.vouchers.index'));

        // Mã luôn lưu in hoa.
        $this->assertDatabaseHas('vouchers', ['code' => 'KHAIGIANG', 'created_by' => $admin->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'voucher.created', 'user_id' => $admin->id]);
    }

    public function test_percent_over_one_hundred_is_rejected(): void
    {
        $this->actingAs($this->makeAdmin())->post(route('admin.vouchers.store'), [
            'code' => 'QUA100',
            'type' => Voucher::TYPE_PERCENT,
            'value' => 150,
            'max_uses_per_user' => 1,
        ])->assertSessionHasErrors('value');
    }

    public function test_a_used_voucher_cannot_be_deleted_only_disabled(): void
    {
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');
        $voucher = $this->voucher();

        $this->actingAs($student)->post(route('packages.voucher.apply', $package), ['code' => 'GIAM50']);
        $this->pay($student, $package);

        $this->actingAs($this->makeAdmin())
            ->from(route('admin.vouchers.index'))
            ->delete(route('admin.vouchers.destroy', $voucher))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('vouchers', ['id' => $voucher->id]);
    }

    public function test_students_cannot_reach_the_admin_voucher_screens(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.vouchers.index'))->assertForbidden();
    }
}
