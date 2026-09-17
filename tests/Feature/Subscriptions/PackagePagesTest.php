<?php

namespace Tests\Feature\Subscriptions;

use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\Package;
use App\Models\Subscription;
use Laravel\Sanctum\Sanctum;

class PackagePagesTest extends SubscriptionTestCase
{
    // --- Bảng giá & mua ------------------------------------------------------------------

    public function test_pricing_page_and_landing_show_prices_from_database(): void
    {
        $this->package('pro-thang')->update(['price' => 123000]);

        $this->get(route('packages.index'))
            ->assertOk()
            ->assertSee('123.000₫')
            ->assertSee('Premium')
            ->assertSee('10 lượt hỏi AI mỗi ngày');

        $this->get(route('home'))->assertOk()->assertSee('123.000₫');
    }

    public function test_inactive_packages_are_hidden_and_cannot_be_checked_out(): void
    {
        $this->package('premium-nam')->update(['is_active' => false]);
        $student = $this->makeStudent();

        $this->get(route('packages.index'))->assertDontSee('1.990.000₫');
        $this->actingAs($student)->get(route('packages.checkout', 'premium-nam'))->assertNotFound();
        $this->actingAs($student)->get(route('packages.checkout', 'free'))->assertNotFound();
    }

    public function test_student_checkout_shows_stacked_start_date(): void
    {
        $student = $this->makeStudent();
        $current = $this->subscribe($student, 'pro-thang');

        $this->actingAs($student)
            ->get(route('packages.checkout', 'pro-nam'))
            ->assertOk()
            ->assertSee($student->name)
            ->assertSee('từ '.$current->ends_at->format('d/m/Y').' (nối tiếp gói cũ)');
    }

    public function test_parent_checks_out_only_for_linked_children(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent('Bé Na');
        $stranger = $this->makeStudent('Người lạ');

        $this->actingAs($parent)->get(route('packages.checkout', 'pro-thang'))
            ->assertOk()->assertSee('Liên kết con');

        $this->link($parent, $child);

        // Chỉ một con → tự chọn.
        $this->actingAs($parent)->get(route('packages.checkout', 'pro-thang'))
            ->assertOk()->assertSee('Người dùng gói')->assertSee('Bé Na');

        $this->actingAs($parent)->get(route('packages.checkout', ['package' => 'pro-thang', 'con' => $stranger->id]))
            ->assertForbidden();
    }

    public function test_teachers_cannot_buy_packages(): void
    {
        $this->actingAs($this->makeTeacher())->get(route('packages.checkout', 'pro-thang'))->assertForbidden();
    }

    public function test_student_subscription_page_shows_plan_and_usage(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('student.subscription.index'))
            ->assertOk()->assertSee('Free')->assertSee('0/10')->assertSee('0/30');

        $this->subscribe($student, 'premium-thang');

        $this->actingAs($student)->get(route('student.subscription.index'))
            ->assertOk()->assertSee('Premium 1 tháng')->assertSee('0/200')->assertSee('không giới hạn');
    }

    public function test_parent_subscription_page_lists_children_plans(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent('Bé Bin');
        $this->link($parent, $child);
        $this->subscribe($child, 'pro-thang', $parent);

        $this->actingAs($parent)->get(route('parent.subscriptions.index'))
            ->assertOk()->assertSee('Bé Bin')->assertSee('Pro 1 tháng')->assertSee('99.000₫');
    }

    // --- Quản trị ---------------------------------------------------------------------

    public function test_admin_changes_price_with_audit_and_old_subscriptions_keep_their_price(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $sub = $this->subscribe($student, 'pro-thang');
        $package = $this->package('pro-thang');

        $this->actingAs($admin)->put(route('admin.packages.update', $package), [
            'name' => 'Pro 1 tháng', 'tier' => 'pro', 'price' => 149000, 'duration_days' => 30,
            'is_active' => 1, 'is_highlighted' => 1, 'is_default' => 0,
            'features' => ['ai_daily_requests' => ['label' => '60 lượt AI', 'limit' => 60, 'show' => 1]],
            'display_lines' => "Toàn bộ bài học Pro\nHỗ trợ ưu tiên",
        ])->assertRedirect(route('admin.packages.index'));

        $package->refresh()->load('features');
        $this->assertSame('149000.00', $package->price);
        $this->assertSame(60, $package->features->firstWhere('key', 'ai.daily_requests')->limit_value);
        $this->assertSame('Hỗ trợ ưu tiên', $package->features->firstWhere('key', 'display.2')->label);
        $this->assertSame('99000.00', $sub->fresh()->price_paid);

        $log = AuditLog::where('action', 'package.updated')->firstOrFail();
        $this->assertEquals(99000, $log->old_values['price']);
        $this->assertEquals(149000, $log->new_values['price']);
    }

    public function test_default_package_rules_are_enforced(): void
    {
        $admin = $this->makeAdmin();
        $free = $this->package('free');

        $this->actingAs($admin)->put(route('admin.packages.update', $free), [
            'name' => 'Free', 'tier' => 'free', 'price' => 0, 'is_active' => 1, 'is_default' => 0,
        ])->assertSessionHasErrors('is_default');

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'name' => 'Pro khuyến mãi', 'tier' => 'pro', 'price' => 50000, 'duration_days' => 7,
            'is_active' => 1, 'is_default' => 1,
        ])->assertSessionHasErrors('is_default');

        $this->actingAs($admin)->delete(route('admin.packages.destroy', $free))->assertSessionHas('error');
        $this->assertTrue($free->fresh()->is_default);
    }

    public function test_admin_can_create_package_and_cannot_delete_sold_package(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.packages.store'), [
            'name' => 'Pro Hè 2026', 'tier' => 'pro', 'price' => 199000, 'duration_days' => 90, 'is_active' => 1,
        ])->assertRedirect(route('admin.packages.index'));

        $package = Package::where('slug', 'pro-he-2026')->firstOrFail();
        $this->assertCount(4, $package->features);

        $this->subscribe($this->makeStudent(), 'pro-he-2026');
        $this->actingAs($admin)->delete(route('admin.packages.destroy', $package))->assertSessionHas('error');
        $this->assertModelExists($package);
    }

    public function test_admin_grants_and_cancels_subscription(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();

        $this->actingAs($admin)->get(route('admin.subscriptions.index'))->assertOk();

        $this->actingAs($admin)->post(route('admin.subscriptions.grant'), [
            'email' => $student->email, 'package_id' => $this->package('premium-thang')->id, 'days' => 14,
        ])->assertSessionHas('status');

        $sub = Subscription::where('user_id', $student->id)->firstOrFail();
        $this->assertTrue($sub->isEffective());
        $this->assertSame(14, $sub->duration_days);

        $this->actingAs($admin)->post(route('admin.subscriptions.cancel', $sub), ['reason' => 'Nhầm tài khoản'])
            ->assertSessionHas('status');
        $this->assertSame(Subscription::STATUS_CANCELLED, $sub->fresh()->status);

        // Không cấp gói cho phụ huynh.
        $this->actingAs($admin)->post(route('admin.subscriptions.grant'), [
            'email' => $this->makeParent()->email, 'package_id' => $this->package('pro-thang')->id, 'days' => 30,
        ])->assertSessionHasErrors('email');
    }

    public function test_non_admin_cannot_manage_packages(): void
    {
        $this->actingAs($this->makeStudent())->get(route('admin.packages.index'))->assertForbidden();
        $this->actingAs($this->makeTeacher())->get(route('admin.subscriptions.index'))->assertForbidden();
    }

    // --- API ---------------------------------------------------------------------------

    public function test_api_lists_packages_and_me_includes_subscription(): void
    {
        $this->getJson(route('api.packages'))
            ->assertOk()
            ->assertJsonPath('data.0.slug', 'free')
            ->assertJsonPath('data.1.price', 99000);

        $student = $this->makeStudent();
        $this->subscribe($student, 'pro-thang');
        Sanctum::actingAs($student);

        $this->getJson(route('api.me'))
            ->assertOk()
            ->assertJsonPath('data.subscription.tier', 'pro')
            ->assertJsonPath('data.subscription.package', 'pro-thang');
    }
}
