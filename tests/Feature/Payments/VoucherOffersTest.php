<?php

namespace Tests\Feature\Payments;

use App\Http\Controllers\Web\VoucherController;
use App\Models\Voucher;
use App\Services\Payment\VoucherService;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

/** Giới thiệu mã giảm giá công khai ở trang Gói học (đợt 25/09). */
class VoucherOffersTest extends SubscriptionTestCase
{
    private function voucher(array $attributes = []): Voucher
    {
        return Voucher::create(array_merge([
            'code' => 'CONGKHAI',
            'type' => Voucher::TYPE_PERCENT,
            'value' => 30,
            'max_uses_per_user' => 1,
            'is_active' => true,
            'is_public' => true,
        ], $attributes));
    }

    public function test_a_public_code_is_advertised_on_the_pricing_page(): void
    {
        $this->voucher(['description' => 'Khai giảng năm học mới']);

        $this->get(route('packages.index'))
            ->assertOk()
            ->assertSee('CONGKHAI')
            ->assertSee('Khai giảng năm học mới')
            ->assertSee('Dùng mã này');
    }

    public function test_a_private_code_is_never_advertised(): void
    {
        // Mã của một chiến dịch riêng đem khoe là ai cũng dùng được.
        $this->voucher(['code' => 'RIENGTU', 'is_public' => false]);

        $this->get(route('packages.index'))->assertOk()->assertDontSee('RIENGTU');
    }

    public function test_codes_that_cannot_be_used_are_not_advertised(): void
    {
        $this->voucher(['code' => 'DATAT', 'is_active' => false]);
        $this->voucher(['code' => 'HETHAN', 'ends_at' => now()->subDay()]);
        $this->voucher(['code' => 'CHUATOI', 'starts_at' => now()->addDay()]);

        // Quảng cáo một mã mà bấm vào báo "không dùng được" còn tệ hơn là không quảng cáo.
        $this->get(route('packages.index'))->assertOk()
            ->assertDontSee('DATAT')
            ->assertDontSee('HETHAN')
            ->assertDontSee('CHUATOI');
    }

    public function test_a_code_that_ran_out_of_uses_disappears(): void
    {
        $voucher = $this->voucher(['code' => 'HETLUOT', 'max_uses' => 1]);
        $student = $this->makeStudent();
        $package = $this->package('pro-thang');

        $this->actingAs($student)->post(route('packages.voucher.apply', $package), ['code' => 'HETLUOT']);
        $this->actingAs($student)->post(route('packages.pay', $package));

        $this->assertSame(1, app(VoucherService::class)->usedCount($voucher));
        $this->get(route('packages.index'))->assertOk()->assertDontSee('HETLUOT');
    }

    public function test_a_guest_can_open_the_advertised_link_without_a_500(): void
    {
        $this->voucher();

        // Phần lớn người bấm link quảng cáo là chưa đăng nhập — trước 25/09 chỗ này nổ 500.
        $this->get(route('packages.index', ['ma' => 'CONGKHAI']))
            ->assertRedirect(route('packages.index'))
            ->assertSessionHas('status');

        $this->assertSame('CONGKHAI', session(VoucherController::SESSION_KEY));
    }

    public function test_the_code_a_guest_picked_still_applies_after_logging_in(): void
    {
        $this->voucher();
        $package = $this->package('pro-thang');

        $this->get(route('packages.index', ['ma' => 'CONGKHAI']));

        $this->actingAs($this->makeStudent())->get(route('packages.checkout', $package))
            ->assertOk()
            ->assertSee('CONGKHAI');
    }

    public function test_the_admin_toggle_controls_it(): void
    {
        $this->actingAs($this->makeAdmin())->post(route('admin.vouchers.store'), [
            'code' => 'TUADMIN',
            'type' => Voucher::TYPE_PERCENT,
            'value' => 20,
            'max_uses_per_user' => 1,
            'is_active' => '1',
            'is_public' => '1',
        ])->assertRedirect();

        $this->assertTrue(Voucher::where('code', 'TUADMIN')->firstOrFail()->is_public);
        $this->get(route('packages.index'))->assertOk()->assertSee('TUADMIN');
    }

    public function test_public_is_off_unless_asked_for(): void
    {
        $this->actingAs($this->makeAdmin())->post(route('admin.vouchers.store'), [
            'code' => 'MACDINH',
            'type' => Voucher::TYPE_PERCENT,
            'value' => 20,
            'max_uses_per_user' => 1,
            'is_active' => '1',
        ]);

        // Mặc định phải là riêng tư: khoe nhầm một mã là ai cũng dùng được.
        $this->assertFalse(Voucher::where('code', 'MACDINH')->firstOrFail()->is_public);
    }
}
