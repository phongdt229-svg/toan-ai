<?php

namespace Tests\Feature\Subscriptions;

use App\Models\AuditLog;
use App\Models\Package;
use App\Models\Subscription;
use RuntimeException;

class SubscriptionServiceTest extends SubscriptionTestCase
{
    public function test_student_without_subscription_uses_default_free_package_limits(): void
    {
        $student = $this->makeStudent();

        $this->assertSame('free', $this->service()->tier($student));
        $this->assertSame(10, $this->service()->limit($student, 'ai.daily_requests'));
        $this->assertSame(30, $this->service()->limit($student, 'practice.daily_questions'));
        $this->assertFalse($this->service()->allows($student, 'ai.advanced_modes'));
    }

    public function test_activation_sets_period_and_is_idempotent(): void
    {
        $this->freezeSecond();
        $student = $this->makeStudent();

        $sub = $this->subscribe($student, 'pro-thang');

        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->status);
        $this->assertTrue($sub->starts_at->equalTo(now()));
        $this->assertTrue($sub->ends_at->equalTo(now()->addDays(30)));
        $this->assertSame('pro', $this->service()->tier($student));
        $this->assertNull($this->service()->limit($student, 'practice.daily_questions'));

        // IPN gửi lại → gọi activate lần nữa không được cộng thêm 30 ngày.
        $again = $this->service()->activate($sub);
        $this->assertTrue($again->ends_at->equalTo(now()->addDays(30)));
    }

    public function test_buying_same_tier_again_stacks_after_current_end(): void
    {
        $this->freezeSecond();
        $student = $this->makeStudent();

        $first = $this->subscribe($student, 'pro-thang');
        $second = $this->subscribe($student, 'pro-nam');

        $this->assertTrue($second->starts_at->equalTo($first->ends_at));
        $this->assertTrue($second->ends_at->equalTo($first->ends_at->copy()->addDays(365)));
        $this->assertSame('Chờ tới lượt', $second->statusLabel());
    }

    public function test_higher_tier_wins_when_both_are_effective(): void
    {
        $student = $this->makeStudent();

        $this->subscribe($student, 'pro-nam');
        $this->subscribe($student, 'premium-thang');

        $this->assertSame('premium', $this->service()->tier($student));
        $this->assertTrue($this->service()->allows($student, 'ai.advanced_modes'));
    }

    public function test_access_ends_at_ends_at_even_before_the_expire_job_runs(): void
    {
        $student = $this->makeStudent();
        $sub = $this->subscribe($student, 'pro-thang');

        $this->travelTo($sub->ends_at->copy()->addMinute());

        $this->assertSame('free', $this->service()->tier($student));
        $this->assertSame(Subscription::STATUS_ACTIVE, $sub->fresh()->status);

        $this->artisan('subscriptions:expire')->assertSuccessful();
        $this->assertSame(Subscription::STATUS_EXPIRED, $sub->fresh()->status);
    }

    public function test_expire_command_cancels_abandoned_pending_subscriptions(): void
    {
        $student = $this->makeStudent();
        $stale = $this->service()->createPending($student, $this->package('pro-thang'));
        $stale->forceFill(['created_at' => now()->subHours(25)])->save();
        $fresh = $this->service()->createPending($student, $this->package('pro-thang'));

        $this->artisan('subscriptions:expire')->assertSuccessful();

        $this->assertSame(Subscription::STATUS_CANCELLED, $stale->fresh()->status);
        $this->assertSame(Subscription::STATUS_PENDING, $fresh->fresh()->status);
    }

    public function test_pending_subscription_snapshots_price_and_records_payer(): void
    {
        $parent = $this->makeParent();
        $student = $this->makeStudent();

        $sub = $this->service()->createPending($student, $this->package('premium-thang'), $parent);
        $this->package('premium-thang')->update(['price' => 1]);

        $this->assertSame('199000.00', $sub->fresh()->price_paid);
        $this->assertSame($parent->id, $sub->purchased_by);
        $this->assertSame($student->id, $sub->user_id);
    }

    public function test_free_package_and_non_students_cannot_be_purchased(): void
    {
        $student = $this->makeStudent();

        $this->expectException(RuntimeException::class);
        $this->service()->createPending($student, $this->package('free'));
    }

    public function test_parent_account_cannot_be_the_beneficiary(): void
    {
        $this->expectException(RuntimeException::class);
        $this->service()->createPending($this->makeParent(), $this->package('pro-thang'));
    }

    public function test_grant_and_cancel_are_audited_and_change_access_immediately(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $this->actingAs($admin);

        $sub = $this->service()->grant($admin, $student, $this->package('premium-thang'), 7);
        $this->assertSame('premium', $this->service()->tier($student));
        $this->assertSame('manual', $sub->source);
        $this->assertSame('0.00', $sub->price_paid);

        $this->service()->cancel($sub, $admin, 'Test huỷ');
        $this->assertSame('free', $this->service()->tier($student));

        $this->assertTrue(AuditLog::where('action', 'subscription.granted')->exists());
        $this->assertTrue(AuditLog::where('action', 'subscription.cancelled')->where('user_id', $admin->id)->exists());
    }

    public function test_without_any_package_configured_nothing_is_locked(): void
    {
        $student = $this->makeStudent();
        Package::query()->each(fn ($p) => $p->features()->delete());
        Package::query()->delete();

        $this->assertFalse($this->service()->limit($student, 'ai.daily_requests'));
        $this->assertTrue($this->service()->allows($student, 'reports.advanced'));
    }
}
