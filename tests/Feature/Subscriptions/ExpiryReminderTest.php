<?php

namespace Tests\Feature\Subscriptions;

use App\Console\Commands\RemindExpiringSubscriptions;
use App\Models\Subscription;
use App\Notifications\SubscriptionExpiringSoon;
use Illuminate\Support\Facades\Notification;

/** Nhắc gói sắp hết hạn ở mốc 7/3/1 ngày (đợt 23/09). */
class ExpiryReminderTest extends SubscriptionTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /** Gói đang chạy, còn đúng $days ngày nữa hết hạn. */
    private function endingIn(int $days, ?string $slug = 'pro-thang'): Subscription
    {
        $student = $this->makeStudent();
        $sub = $this->subscribe($student, $slug);
        $sub->forceFill(['ends_at' => now()->addDays($days)->subHour()])->save();

        return $sub->refresh();
    }

    public function test_it_reminds_at_seven_three_and_one_day(): void
    {
        foreach (RemindExpiringSubscriptions::THRESHOLDS as $days) {
            Notification::fake();
            $sub = $this->endingIn($days);

            $this->artisan('subscriptions:remind-expiring')->assertSuccessful();

            Notification::assertSentTo($sub->user, SubscriptionExpiringSoon::class);
            $this->assertSame($days, $sub->refresh()->expiry_reminded_days);
        }
    }

    public function test_it_stays_quiet_while_the_package_still_has_time(): void
    {
        $sub = $this->endingIn(20);

        $this->artisan('subscriptions:remind-expiring')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($sub->refresh()->expiry_reminded_days);
    }

    public function test_running_twice_in_a_day_does_not_send_twice(): void
    {
        $sub = $this->endingIn(7);

        $this->artisan('subscriptions:remind-expiring');
        $this->artisan('subscriptions:remind-expiring');

        Notification::assertSentToTimes($sub->user, SubscriptionExpiringSoon::class, 1);
    }

    public function test_a_closer_threshold_reminds_again(): void
    {
        $sub = $this->endingIn(7);
        $this->artisan('subscriptions:remind-expiring');

        // Vài ngày sau, còn 1 ngày → phải nhắc thêm lần nữa.
        $sub->forceFill(['ends_at' => now()->addHours(20)])->save();
        $this->artisan('subscriptions:remind-expiring');

        Notification::assertSentToTimes($sub->user, SubscriptionExpiringSoon::class, 2);
        $this->assertSame(1, $sub->refresh()->expiry_reminded_days);
    }

    public function test_the_parent_who_paid_is_told_as_well(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $this->link($parent, $child);

        $sub = $this->subscribe($child, 'pro-thang', $parent);
        $sub->forceFill(['ends_at' => now()->addDays(3)->subHour()])->save();

        $this->artisan('subscriptions:remind-expiring')->assertSuccessful();

        // Người trả tiền mới là người quyết định gia hạn.
        Notification::assertSentTo($parent, SubscriptionExpiringSoon::class);
        Notification::assertSentTo($child, SubscriptionExpiringSoon::class);
    }

    public function test_free_packages_are_never_reminded(): void
    {
        // Gói Free không mua qua luồng bình thường được, nên dựng thẳng bản ghi
        // để kiểm đúng cái chốt trong lệnh.
        $sub = Subscription::create([
            'user_id' => $this->makeStudent()->id,
            'package_id' => $this->package('free')->id,
            'status' => Subscription::STATUS_ACTIVE,
            'price_paid' => 0,
            'duration_days' => 30,
            'starts_at' => now()->subDays(29),
            'ends_at' => now()->addHours(20),
            'activated_at' => now()->subDays(29),
        ]);

        $this->artisan('subscriptions:remind-expiring')->assertSuccessful();

        Notification::assertNothingSent();
        $this->assertNull($sub->refresh()->expiry_reminded_days);
    }

    public function test_renewing_rearms_the_reminder(): void
    {
        $sub = $this->endingIn(1);
        $this->artisan('subscriptions:remind-expiring');
        $this->assertSame(1, $sub->refresh()->expiry_reminded_days);

        $renewed = $this->service()->activate(
            $this->service()->createPending($sub->user, $this->package('pro-thang'))
        );

        $this->assertNull($renewed->expiry_reminded_days);
    }

    public function test_users_can_mute_the_in_app_notification_but_still_get_the_email(): void
    {
        $sub = $this->endingIn(1);
        $sub->user->forceFill(['notification_preferences' => [SubscriptionExpiringSoon::class]])->save();

        $channels = (new SubscriptionExpiringSoon($sub, 1))->via($sub->user->refresh());

        // Tắt chuông thì thôi chuông, nhưng email nhắc tiền vẫn phải tới.
        $this->assertSame(['mail'], $channels);
    }
}
