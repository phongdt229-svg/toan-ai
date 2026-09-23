<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TwoFactorTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['password' => 'secret-Pass1']);
        $admin->assignRole('admin');

        return $admin;
    }

    /** Bật 2FA qua service và trả về [secret, recoveryCodes]. */
    private function enable(User $user): array
    {
        $service = app(TwoFactorService::class);
        $secret = $service->beginSetup($user);
        $codes = $service->confirm($user->refresh(), $service->codeAt($secret, time()));

        return [$secret, $codes];
    }

    public function test_totp_matches_rfc_6238_vector(): void
    {
        // Secret "12345678901234567890" (base32 GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ), t=59 → 94287082; lấy 6 số cuối.
        $this->assertSame('287082', app(TwoFactorService::class)->codeAt('GEZDGNBVGY3TQOJQGEZDGNBVGY3TQOJQ', 59));
    }

    public function test_password_alone_is_not_enough_when_2fa_is_on(): void
    {
        $admin = $this->admin();
        [$secret] = $this->enable($admin);

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'secret-Pass1'])
            ->assertRedirect(route('two-factor.challenge'));

        $this->assertGuest();
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }

    public function test_valid_code_completes_login_and_cannot_be_replayed(): void
    {
        $admin = $this->admin();
        [$secret] = $this->enable($admin);
        $service = app(TwoFactorService::class);

        // Bước đã dùng lúc xác nhận khi bật → phải đợi bước kế (mô phỏng bằng cách lùi last_step).
        $admin->forceFill(['two_factor_last_step' => intdiv(time(), 30) - 5])->save();

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'secret-Pass1']);
        $code = $service->codeAt($secret, time());

        $this->post(route('two-factor.verify'), ['code' => $code])->assertRedirect();
        $this->assertAuthenticatedAs($admin);

        auth()->logout();
        $this->flushSession();
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'secret-Pass1']);
        $this->post(route('two-factor.verify'), ['code' => $code])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_wrong_code_is_rejected(): void
    {
        $admin = $this->admin();
        $this->enable($admin);

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'secret-Pass1']);
        $this->post(route('two-factor.verify'), ['code' => '000000'])->assertSessionHasErrors('code');
        $this->assertGuest();
    }

    public function test_recovery_code_works_once(): void
    {
        $admin = $this->admin();
        [, $codes] = $this->enable($admin);

        $this->post(route('login'), ['email' => $admin->email, 'password' => 'secret-Pass1']);
        $this->post(route('two-factor.verify'), ['code' => $codes[0]])->assertRedirect();
        $this->assertAuthenticatedAs($admin);

        auth()->logout();
        $this->flushSession();
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'secret-Pass1']);
        $this->post(route('two-factor.verify'), ['code' => $codes[0]])->assertSessionHasErrors('code');
    }

    public function test_setup_needs_confirmation_before_it_counts(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.two-factor.enable'), ['current_password' => 'secret-Pass1'])->assertRedirect();
        $this->assertFalse(app(TwoFactorService::class)->isEnabled($admin->refresh()));

        // Chưa xác nhận → đăng nhập vẫn chỉ cần mật khẩu, không bị khoá.
        auth()->logout();
        $this->post(route('login'), ['email' => $admin->email, 'password' => 'secret-Pass1'])
            ->assertRedirect();
        $this->assertAuthenticatedAs($admin);
    }

    public function test_disable_requires_current_password(): void
    {
        $admin = $this->admin();
        $this->enable($admin);

        $this->actingAs($admin)->delete(route('admin.two-factor.disable'), ['current_password' => 'nope'])
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(app(TwoFactorService::class)->isEnabled($admin->refresh()));

        $this->delete(route('admin.two-factor.disable'), ['current_password' => 'secret-Pass1']);
        $this->assertFalse(app(TwoFactorService::class)->isEnabled($admin->refresh()));
    }

    public function test_admin_without_2fa_is_sent_to_settings_when_required(): void
    {
        $admin = $this->admin();
        config(['auth.require_admin_2fa' => true]);

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertRedirect(route('admin.settings'));
        $this->get(route('admin.settings'))->assertOk();

        $this->enable($admin);
        $this->get(route('admin.dashboard'))->assertOk();
    }
}
