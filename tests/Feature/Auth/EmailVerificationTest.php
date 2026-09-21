<?php

namespace Tests\Feature\Auth;

use App\Models\Grade;
use App\Models\Package;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\VerifyEmailLink;
use Database\Seeders\GradeSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
        Notification::fake();
    }

    private function unverifiedStudent(): User
    {
        $user = User::factory()->unverified()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $user->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        return $user;
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($user->email),
        ]);
    }

    // --- Gửi mail ----------------------------------------------------------------------

    public function test_registering_sends_a_vietnamese_verification_mail(): void
    {
        $this->post(route('register.student'), [
            'name' => 'Nguyễn Tân Binh',
            'email' => 'tanbinh@example.com',
            'password' => 'matkhau123',
            'password_confirmation' => 'matkhau123',
            'grade_id' => Grade::where('level', 6)->value('id'),
        ])->assertSessionHasNoErrors();

        $user = User::where('email', 'tanbinh@example.com')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        Notification::assertSentTo($user, VerifyEmailLink::class, function (VerifyEmailLink $mail) use ($user) {
            $content = $mail->toMail($user);

            return $content->subject === 'Xác thực email TOÁN AI'
                && str_contains($content->actionUrl, '/xac-thuc-email/'.$user->id.'/');
        });
    }

    public function test_user_can_resend_the_mail_and_it_stops_once_verified(): void
    {
        $user = $this->unverifiedStudent();

        $this->actingAs($user)->post(route('verification.send'))->assertSessionHas('status');
        Notification::assertSentToTimes($user, VerifyEmailLink::class, 1);

        $user->markEmailAsVerified();
        $this->actingAs($user->fresh())->post(route('verification.send'));
        Notification::assertSentToTimes($user, VerifyEmailLink::class, 1);
    }

    // --- Mở link -----------------------------------------------------------------------

    public function test_signed_link_verifies_the_account(): void
    {
        Event::fake([Verified::class]);
        $user = $this->unverifiedStudent();

        $this->actingAs($user)->get($this->verificationUrl($user))
            ->assertRedirect(route('student.dashboard'))
            ->assertSessionHas('status');

        $this->assertNotNull($user->fresh()->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_tampered_or_expired_link_is_rejected(): void
    {
        $user = $this->unverifiedStudent();
        $other = $this->unverifiedStudent();

        // Đổi id trên URL đã ký → chữ ký không còn khớp.
        $tampered = str_replace("/{$user->id}/", "/{$other->id}/", $this->verificationUrl($user));
        $this->actingAs($user)->get($tampered)->assertForbidden();

        $url = $this->verificationUrl($user);
        $this->travel(61)->minutes();
        $this->actingAs($user)->get($url)->assertForbidden();

        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_hash_of_another_email_is_rejected(): void
    {
        $user = $this->unverifiedStudent();

        $url = URL::temporarySignedRoute('verification.verify', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1('email-khac@example.com'),
        ]);

        $this->actingAs($user)->get($url)->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    // --- Ảnh hưởng tới việc dùng hệ thống ------------------------------------------------

    public function test_unverified_user_can_still_learn_but_sees_a_reminder(): void
    {
        $user = $this->unverifiedStudent();

        $this->actingAs($user)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('chưa được xác thực', false);
    }

    public function test_buying_a_package_requires_a_verified_email(): void
    {
        $this->seed(PackageSeeder::class);
        $package = Package::where('slug', 'pro-thang')->firstOrFail();
        $user = $this->unverifiedStudent();

        $this->actingAs($user)->get(route('packages.checkout', $package))
            ->assertRedirect(route('verification.notice'));

        $this->actingAs($user)->post(route('packages.pay', $package))
            ->assertRedirect(route('verification.notice'));

        $user->markEmailAsVerified();
        $this->actingAs($user->fresh())->get(route('packages.checkout', $package))->assertOk();
    }

    public function test_notice_page_redirects_verified_users_away(): void
    {
        $user = $this->unverifiedStudent();

        $this->actingAs($user)->get(route('verification.notice'))->assertOk()->assertSee('Xác thực email');

        $user->markEmailAsVerified();
        $this->actingAs($user->fresh())->get(route('verification.notice'))
            ->assertRedirect(route('student.dashboard'));
    }
}
