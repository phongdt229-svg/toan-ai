<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\User;
use App\Notifications\ResetPasswordLink;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

class PasswordResetTest extends TestCase
{
    use RefreshDatabase;

    private const GENERIC = 'Nếu email này có tài khoản';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
        Notification::fake();
    }

    private function makeUser(string $status = User::STATUS_ACTIVE): User
    {
        $user = User::factory()->create(['status' => $status, 'password' => Hash::make('matkhaucu1')]);
        $user->assignRole(Role::STUDENT);

        return $user;
    }

    /** Lấy token thật từ mail đã gửi để đi tiếp bước đặt lại. */
    private function tokenFor(User $user): string
    {
        $token = null;
        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $mail) use (&$token) {
            $token = $mail->token;

            return true;
        });

        return $token;
    }

    // --- Yêu cầu link -------------------------------------------------------------------

    public function test_login_page_links_to_forgot_password_form(): void
    {
        $this->get(route('login'))->assertOk()->assertSee(route('password.request'));
        $this->get(route('password.request'))->assertOk()->assertSee('Quên mật khẩu');
    }

    public function test_link_is_sent_in_vietnamese_with_a_working_reset_url(): void
    {
        $user = $this->makeUser();

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHas('status')
            ->assertSessionHasNoErrors();

        Notification::assertSentTo($user, ResetPasswordLink::class, function (ResetPasswordLink $notification) use ($user) {
            $mail = $notification->toMail($user);
            $url = route('password.reset', ['token' => $notification->token, 'email' => $user->email]);

            return $mail->subject === 'Đặt lại mật khẩu TOÁN AI'
                && $mail->actionUrl === $url
                && str_contains(implode(' ', $mail->introLines), 'đặt lại mật khẩu');
        });

        // Không dùng mail mặc định tiếng Anh của Laravel.
        Notification::assertNotSentTo($user, ResetPassword::class);
    }

    public function test_unknown_email_gets_the_same_answer_and_no_mail(): void
    {
        $this->from(route('password.request'))
            ->post(route('password.email'), ['email' => 'khongcoai@toan-ai.local'])
            ->assertRedirect(route('password.request'))
            ->assertSessionHas('status', fn ($status) => str_contains($status, self::GENERIC));

        Notification::assertNothingSent();
    }

    public function test_suspended_and_rejected_accounts_cannot_get_a_reset_link(): void
    {
        foreach ([User::STATUS_SUSPENDED, User::STATUS_REJECTED] as $status) {
            $user = $this->makeUser($status);

            $this->post(route('password.email'), ['email' => $user->email])
                ->assertSessionHas('status', fn ($message) => str_contains($message, self::GENERIC));

            Notification::assertNotSentTo($user, ResetPasswordLink::class);
        }
    }

    public function test_pending_teacher_can_still_reset(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_PENDING]);
        $teacher->assignRole(Role::TEACHER);

        $this->post(route('password.email'), ['email' => $teacher->email])->assertSessionHasNoErrors();

        Notification::assertSentTo($teacher, ResetPasswordLink::class);
    }

    public function test_requests_are_throttled_after_five_tries(): void
    {
        $user = $this->makeUser();

        for ($i = 0; $i < 5; $i++) {
            $this->post(route('password.email'), ['email' => $user->email])->assertSessionHasNoErrors();
        }

        $this->post(route('password.email'), ['email' => $user->email])
            ->assertSessionHasErrors('email');
    }

    // --- Đặt mật khẩu mới ---------------------------------------------------------------

    public function test_reset_changes_password_logs_out_other_devices_and_is_audited(): void
    {
        $user = $this->makeUser();
        $user->createToken('dien-thoai');

        $this->post(route('password.email'), ['email' => $user->email]);
        $token = $this->tokenFor($user);

        $this->get(route('password.reset', ['token' => $token, 'email' => $user->email]))
            ->assertOk()
            ->assertSee($user->email);

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'matkhaumoi9',
            'password_confirmation' => 'matkhaumoi9',
        ])->assertRedirect(route('login'))->assertSessionHas('status');

        $user->refresh();
        $this->assertTrue(Hash::check('matkhaumoi9', $user->password));
        $this->assertSame(0, $user->tokens()->count());
        $this->assertTrue(AuditLog::where('action', 'user.password_reset')->where('auditable_id', $user->id)->exists());

        // Đăng nhập được bằng mật khẩu mới, mật khẩu cũ hết tác dụng.
        $this->post(route('login'), ['email' => $user->email, 'password' => 'matkhaucu1'])->assertSessionHasErrors();
        $this->post(route('login'), ['email' => $user->email, 'password' => 'matkhaumoi9'])->assertSessionHasNoErrors();
        $this->assertAuthenticatedAs($user);
    }

    public function test_token_works_only_once(): void
    {
        $user = $this->makeUser();
        $this->post(route('password.email'), ['email' => $user->email]);
        $token = $this->tokenFor($user);

        $payload = [
            'token' => $token,
            'email' => $user->email,
            'password' => 'matkhaumoi9',
            'password_confirmation' => 'matkhaumoi9',
        ];

        $this->post(route('password.update'), $payload)->assertSessionHasNoErrors();
        $this->post(route('password.update'), $payload)->assertSessionHasErrors('email');
    }

    public function test_expired_or_wrong_token_is_rejected(): void
    {
        $user = $this->makeUser();

        $this->post(route('password.update'), [
            'token' => 'token-gia',
            'email' => $user->email,
            'password' => 'matkhaumoi9',
            'password_confirmation' => 'matkhaumoi9',
        ])->assertSessionHasErrors('email');

        $this->post(route('password.email'), ['email' => $user->email]);
        $token = $this->tokenFor($user);

        // Link sống 60 phút theo config/auth.php.
        $this->travel(61)->minutes();

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => $user->email,
            'password' => 'matkhaumoi9',
            'password_confirmation' => 'matkhaumoi9',
        ])->assertSessionHasErrors('email');

        $this->assertTrue(Hash::check('matkhaucu1', $user->fresh()->password));
        $this->assertFalse(Password::tokenExists($user, $token), 'Token quá 60 phút phải hết hiệu lực.');
    }

    public function test_weak_or_mismatched_password_is_rejected(): void
    {
        $user = $this->makeUser();
        $this->post(route('password.email'), ['email' => $user->email]);
        $token = $this->tokenFor($user);

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => '123', 'password_confirmation' => '123',
        ])->assertSessionHasErrors('password');

        $this->post(route('password.update'), [
            'token' => $token, 'email' => $user->email,
            'password' => 'matkhaumoi9', 'password_confirmation' => 'khacnhau9',
        ])->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('matkhaucu1', $user->fresh()->password));
    }

    public function test_logged_in_user_is_redirected_away_from_reset_pages(): void
    {
        $this->actingAs($this->makeUser())
            ->get(route('password.request'))
            ->assertRedirect();
    }
}
