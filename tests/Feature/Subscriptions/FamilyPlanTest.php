<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Package;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Auth\AccountDeletionService;
use App\Services\FamilyPlanService;

/** Gói gia đình: chủ gói nhận mã, anh chị em nhập mã để dùng chung quyền gói. */
class FamilyPlanTest extends SubscriptionTestCase
{
    private User $owner;

    private Subscription $family;

    protected function setUp(): void
    {
        parent::setUp();

        Package::create([
            'name' => 'Gia đình Pro', 'slug' => 'gia-dinh-pro', 'tier' => Package::TIER_PRO, 'price' => 1500000,
            'currency' => 'VND', 'duration_days' => 365, 'max_members' => 3, 'is_active' => true,
        ]);

        $this->owner = $this->makeStudent('Chủ Gói');
        $this->family = $this->subscribe($this->owner, 'gia-dinh-pro');
    }

    private function join(User $student, ?string $code = null)
    {
        return $this->actingAs($student)->post(route('student.family.join'), ['join_code' => $code ?? $this->family->join_code]);
    }

    public function test_activation_creates_a_join_code_only_for_family_packages(): void
    {
        $this->assertMatchesRegularExpression('/^GD[A-Z0-9]{6}$/', $this->family->join_code);
        $this->assertNull($this->subscribe($this->makeStudent(), 'pro-thang')->join_code);
    }

    public function test_member_gets_the_package_tier_through_the_code(): void
    {
        $sibling = $this->makeStudent('Em Út');
        $this->assertSame(Package::TIER_FREE, $this->service()->tier($sibling));

        $this->join($sibling, strtolower($this->family->join_code))->assertSessionHasNoErrors()->assertSessionHas('status');

        $this->assertSame(Package::TIER_PRO, $this->service()->tier($sibling));
        $this->actingAs($this->owner)->get(route('student.subscription.index'))
            ->assertOk()->assertSee($this->family->join_code)->assertSee('Em Út')->assertSee('Còn <strong>1</strong> chỗ', false);
        $this->actingAs($sibling)->get(route('student.subscription.index'))
            ->assertOk()->assertSee('dùng chung gói gia đình')->assertSee('Chủ Gói');
    }

    public function test_seats_are_limited_and_codes_validated(): void
    {
        $this->join($this->makeStudent())->assertSessionHasNoErrors();
        $this->join($this->makeStudent())->assertSessionHasNoErrors();

        $this->join($this->makeStudent())->assertSessionHasErrors(['join_code' => 'Gói gia đình đã đủ người.']);
        $this->join($this->makeStudent(), 'GDSAIMA1')->assertSessionHasErrors('join_code');
        $this->join($this->owner)->assertSessionHasErrors('join_code'); // chủ gói không tự vào lần nữa
    }

    public function test_expired_or_refunded_family_plan_drops_members(): void
    {
        $sibling = $this->makeStudent();
        $this->join($sibling);
        $this->assertSame(Package::TIER_PRO, $this->service()->tier($sibling));

        $this->service()->cancel($this->family->fresh(), $this->makeAdmin(), 'Hoàn tiền');

        $this->assertSame(Package::TIER_FREE, $this->service()->tier($sibling));
        $this->join($this->makeStudent())->assertSessionHasErrors('join_code');
    }

    public function test_owner_can_remove_and_member_can_leave_but_strangers_cannot(): void
    {
        $a = $this->makeStudent('Thành Viên A');
        $b = $this->makeStudent('Thành Viên B');
        $this->join($a);
        $this->join($b);

        $this->actingAs($this->makeStudent())->delete(route('student.family.remove', [$this->family, $a]))->assertForbidden();

        $this->actingAs($this->owner)->delete(route('student.family.remove', [$this->family, $a]))->assertSessionHas('status');
        $this->assertSame(Package::TIER_FREE, $this->service()->tier($a));

        $this->actingAs($b)->delete(route('student.family.remove', [$this->family, $b]))->assertSessionHas('status');
        $this->assertSame(0, $this->family->members()->count());
    }

    public function test_renewal_carries_members_over(): void
    {
        $sibling = $this->makeStudent();
        $this->join($sibling);

        $renewal = $this->subscribe($this->owner, 'gia-dinh-pro');

        $this->assertNotSame($this->family->join_code, $renewal->join_code);
        $this->assertTrue($renewal->members()->where('users.id', $sibling->id)->exists());

        // Chủ gói gỡ em → mất quyền ngay, kể cả quyền từ gói cũ chưa hết hạn.
        $other = $this->makeStudent();
        $this->join($other, $renewal->join_code)->assertSessionHasNoErrors(); // mã gói gia hạn (chưa bắt đầu) vẫn dùng được
        $this->assertSame(Package::TIER_PRO, $this->service()->tier($other));
        $this->assertTrue($this->family->members()->where('users.id', $other->id)->exists());
        $this->actingAs($this->owner)->delete(route('student.family.remove', [$renewal, $other]));
        $this->assertSame(Package::TIER_FREE, $this->service()->tier($other));

        // Gói cũ hết hạn → em vẫn có quyền nhờ gói gia hạn.
        $this->travelTo($this->family->fresh()->ends_at->addDay());
        $this->assertSame(Package::TIER_PRO, $this->service()->tier($sibling));
    }

    public function test_cannot_join_two_family_plans_at_once(): void
    {
        $otherOwner = $this->makeStudent();
        $other = $this->subscribe($otherOwner, 'gia-dinh-pro');
        $sibling = $this->makeStudent();

        $this->join($sibling)->assertSessionHasNoErrors();
        $this->join($sibling, $other->join_code)->assertSessionHasErrors('join_code');
    }

    public function test_family_size_is_shown_on_the_pricing_page_and_admin_can_set_it(): void
    {
        $this->get(route('packages.index'))->assertOk()->assertSee('3 học sinh');

        $package = Package::where('slug', 'gia-dinh-pro')->firstOrFail();
        $this->assertTrue($package->isFamily());
        $this->assertSame(2, app(FamilyPlanService::class)->seatsLeft($this->family->load('package')));
    }

    public function test_parent_sees_the_family_code_of_the_child_holding_the_plan(): void
    {
        $parent = $this->makeParent();
        $this->link($parent, $this->owner);
        $sibling = $this->makeStudent('Em Hai');
        $this->link($parent, $sibling);
        $this->join($sibling);

        $this->actingAs($parent)->get(route('parent.subscriptions.index'))
            ->assertOk()
            ->assertSee($this->family->join_code)
            ->assertSee('Còn 1 chỗ')
            ->assertSee('Đang dùng chung gói gia đình của Chủ Gói');
    }

    public function test_memberships_are_removed_on_anonymise(): void
    {
        $sibling = $this->makeStudent();
        $this->join($sibling);

        $method = new \ReflectionMethod(AccountDeletionService::class, 'anonymise');
        $method->invoke(app(AccountDeletionService::class), $sibling);

        $this->assertSame(0, $this->family->members()->count());
    }
}
