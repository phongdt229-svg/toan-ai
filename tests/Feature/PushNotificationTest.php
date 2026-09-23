<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\PushSubscription;
use App\Models\Role;
use App\Models\User;
use App\Notifications\AssignmentDueSoon;
use App\Notifications\Channels\WebPushChannel;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Đăng ký / huỷ nhận thông báo đẩy (đợt 23/09). */
class PushNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
        config(['push.public_key' => 'khoa-cong-khai', 'push.private_key' => 'khoa-rieng']);
    }

    private function user(): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::STUDENT);

        return $user;
    }

    /** @return array<string, mixed> */
    private function payload(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): array
    {
        return ['endpoint' => $endpoint, 'keys' => ['p256dh' => 'khoa-p256', 'auth' => 'khoa-auth']];
    }

    public function test_a_browser_can_register_to_receive_push(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson(route('push.store'), $this->payload())
            ->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('push_subscriptions', [
            'user_id' => $user->id,
            'endpoint' => 'https://fcm.googleapis.com/fcm/send/abc',
        ]);
    }

    public function test_registering_the_same_browser_twice_does_not_duplicate(): void
    {
        $user = $this->user();

        $this->actingAs($user)->postJson(route('push.store'), $this->payload());
        $this->actingAs($user)->postJson(route('push.store'), $this->payload());

        $this->assertDatabaseCount('push_subscriptions', 1);
    }

    public function test_a_user_can_turn_it_off_for_one_device(): void
    {
        $user = $this->user();
        $this->actingAs($user)->postJson(route('push.store'), $this->payload('https://a.example/1'));
        $this->actingAs($user)->postJson(route('push.store'), $this->payload('https://a.example/2'));

        $this->actingAs($user)->deleteJson(route('push.destroy'), ['endpoint' => 'https://a.example/1'])
            ->assertOk();

        $this->assertDatabaseMissing('push_subscriptions', ['endpoint' => 'https://a.example/1']);
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://a.example/2']);
    }

    public function test_nobody_can_delete_someone_elses_device(): void
    {
        $owner = $this->user();
        $stranger = $this->user();
        $this->actingAs($owner)->postJson(route('push.store'), $this->payload('https://a.example/cua-toi'));

        $this->actingAs($stranger)->deleteJson(route('push.destroy'), ['endpoint' => 'https://a.example/cua-toi'])
            ->assertOk();

        // Xoá theo endpoint người khác gửi lên thì không được đụng tới.
        $this->assertDatabaseHas('push_subscriptions', ['endpoint' => 'https://a.example/cua-toi']);
    }

    public function test_registration_is_refused_when_the_server_has_no_keys(): void
    {
        config(['push.public_key' => '', 'push.private_key' => '']);

        $this->actingAs($this->user())->postJson(route('push.store'), $this->payload())->assertNotFound();
        $this->assertFalse(WebPushChannel::configured());
    }

    public function test_a_malformed_subscription_is_rejected(): void
    {
        $this->actingAs($this->user())
            ->postJson(route('push.store'), ['endpoint' => 'khong-phai-url', 'keys' => []])
            ->assertJsonValidationErrors(['endpoint', 'keys.p256dh', 'keys.auth']);
    }

    public function test_guests_cannot_register(): void
    {
        $this->postJson(route('push.store'), $this->payload())->assertUnauthorized();
    }

    public function test_the_channel_does_nothing_without_a_subscription(): void
    {
        // Không có thiết bị nào đăng ký thì kênh phải im lặng, không nổ.
        $user = $this->user();
        $this->assertSame(0, PushSubscription::where('user_id', $user->id)->count());

        (new WebPushChannel)->send($user, new AssignmentDueSoon(
            new Assignment(['title' => 'Bài 1', 'due_at' => now()->addHours(3)]), 3
        ));

        $this->assertTrue(true);
    }
}
