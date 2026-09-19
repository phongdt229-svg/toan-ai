<?php

namespace Tests\Feature;

use App\Models\Package;
use App\Models\Payment;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentSucceeded;
use App\Notifications\TeacherAccountApproved;
use App\Support\NotificationType;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

/** Cài đặt bật/tắt từng loại thông báo (resources/views/partials/notification-preferences-form.blade.php). */
class NotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    private function makeUser(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::TEACHER);

        return $user;
    }

    public function test_settings_page_lists_every_notification_type(): void
    {
        $user = $this->makeUser();

        $response = $this->actingAs($user)->get(route('teacher.settings'))->assertOk();

        foreach (NotificationType::LABELS as $label) {
            $response->assertSee($label);
        }
    }

    public function test_unchecking_a_type_mutes_only_that_type(): void
    {
        $user = $this->makeUser();
        $keep = array_keys(NotificationType::LABELS);
        $muted = array_shift($keep); // 1 loại bị bỏ khỏi danh sách "enabled" gửi lên = bị tắt

        $this->actingAs($user)
            ->put(route('notifications.preferences.update'), ['enabled' => $keep])
            ->assertRedirect();

        $user->refresh();
        $this->assertTrue($user->hasMutedNotification($muted));

        foreach ($keep as $type) {
            $this->assertFalse($user->hasMutedNotification($type));
        }
    }

    public function test_unchecking_everything_mutes_all_types(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->put(route('notifications.preferences.update'), [])->assertRedirect();

        $user->refresh();
        foreach (array_keys(NotificationType::LABELS) as $type) {
            $this->assertTrue($user->hasMutedNotification($type));
        }
    }

    public function test_unknown_type_key_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)
            ->put(route('notifications.preferences.update'), ['enabled' => ['App\\Not\\A\\Real\\Notification']])
            ->assertSessionHasErrors('enabled.0');
    }

    public function test_muted_type_creates_no_database_notification(): void
    {
        $user = $this->makeUser();
        $user->update(['notification_preferences' => [TeacherAccountApproved::class]]);

        $user->notify(new TeacherAccountApproved);

        $this->assertSame(0, $user->notifications()->count());
    }

    public function test_unmuted_type_still_creates_a_notification(): void
    {
        $user = $this->makeUser();
        $user->update(['notification_preferences' => []]); // không tắt gì

        $user->notify(new TeacherAccountApproved);

        $this->assertSame(1, $user->notifications()->count());
    }

    /** Email biên nhận thanh toán không cho tắt — chỉ chuông trong app theo được cài đặt. */
    public function test_muting_payment_notification_still_sends_the_receipt_email(): void
    {
        Notification::fake();

        $this->seed(RolePermissionSeeder::class);
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole(Role::STUDENT);
        $student->update(['notification_preferences' => [PaymentSucceeded::class]]);

        $package = Package::create([
            'name' => 'Pro 1 tháng', 'slug' => 'pro-thang-pref-test', 'tier' => Package::TIER_PRO,
            'price' => 99000, 'currency' => 'VND', 'duration_days' => 30, 'is_active' => true,
        ]);
        $subscription = Subscription::create([
            'user_id' => $student->id, 'purchased_by' => $student->id, 'package_id' => $package->id,
            'status' => 'active', 'price_paid' => $package->price, 'duration_days' => 30,
            'starts_at' => now(), 'ends_at' => now()->addDays(30), 'activated_at' => now(), 'source' => 'payment',
        ]);
        $payment = Payment::create([
            'order_code' => 'PREFTEST'.uniqid(), 'user_id' => $student->id, 'package_id' => $package->id,
            'subscription_id' => $subscription->id, 'amount' => $package->price,
            'status' => Payment::STATUS_PAID, 'paid_at' => now(),
        ]);

        $student->notify(new PaymentSucceeded($payment));

        Notification::assertSentTo($student, PaymentSucceeded::class, function ($notification, $channels) {
            return in_array('mail', $channels, true) && ! in_array('database', $channels, true);
        });
    }
}
