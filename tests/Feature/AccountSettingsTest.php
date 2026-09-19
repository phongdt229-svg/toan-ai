<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Hồ sơ + đổi mật khẩu — cùng một cặp form dùng chung cho cả 4 portal
 * (resources/views/partials/profile-form.blade.php + password-form.blade.php).
 */
class AccountSettingsTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<int, array{0: string, 1: string}> Cặp [role, route prefix] cho 4 portal. */
    public static function portals(): array
    {
        return [
            'student' => [Role::STUDENT, 'student'],
            'teacher' => [Role::TEACHER, 'teacher'],
            'parent' => [Role::PARENT, 'parent'],
            'admin' => [Role::ADMIN, 'admin'],
        ];
    }

    private function makeUser(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE, 'phone' => '0900000000']);
        $user->assignRole($role);

        return $user;
    }

    /** @dataProvider portals */
    public function test_settings_page_shows_current_profile(string $role, string $prefix): void
    {
        $user = $this->makeUser($role);

        $this->actingAs($user)->get(route("{$prefix}.settings"))
            ->assertOk()
            ->assertSee($user->name)
            ->assertSee($user->email);
    }

    /** @dataProvider portals */
    public function test_updating_profile_saves_name_and_phone(string $role, string $prefix): void
    {
        $user = $this->makeUser($role);

        $this->actingAs($user)
            ->put(route("{$prefix}.settings.profile"), ['name' => 'Tên mới', 'phone' => '0987654321'])
            ->assertRedirect()
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('Tên mới', $user->name);
        $this->assertSame('0987654321', $user->phone);
    }

    /** @dataProvider portals */
    public function test_profile_requires_a_name(string $role, string $prefix): void
    {
        $user = $this->makeUser($role);

        $this->actingAs($user)
            ->put(route("{$prefix}.settings.profile"), ['name' => ''])
            ->assertSessionHasErrors('name');
    }

    /** @dataProvider portals */
    public function test_changing_password_requires_correct_current_password(string $role, string $prefix): void
    {
        $user = $this->makeUser($role); // factory mặc định 'password' cho mật khẩu, xem UserFactory

        $this->actingAs($user)
            ->put(route("{$prefix}.settings.password"), [
                'current_password' => 'sai-mat-khau',
                'password' => 'MatKhauMoi123',
                'password_confirmation' => 'MatKhauMoi123',
            ])
            ->assertSessionHasErrors('current_password');
    }

    /** @dataProvider portals */
    public function test_changing_password_with_correct_current_password_logs_in_with_new_one(string $role, string $prefix): void
    {
        $user = $this->makeUser($role);

        $this->actingAs($user)
            ->put(route("{$prefix}.settings.password"), [
                'current_password' => 'password',
                'password' => 'MatKhauMoi123',
                'password_confirmation' => 'MatKhauMoi123',
            ])
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertTrue(Hash::check('MatKhauMoi123', $user->fresh()->password));
    }

    /** @dataProvider portals */
    public function test_new_password_must_meet_complexity_rules(string $role, string $prefix): void
    {
        $user = $this->makeUser($role);

        $this->actingAs($user)
            ->put(route("{$prefix}.settings.password"), [
                'current_password' => 'password',
                'password' => 'short',
                'password_confirmation' => 'short',
            ])
            ->assertSessionHasErrors('password');
    }

    public function test_guest_cannot_reach_any_settings_page(): void
    {
        $this->get(route('student.settings'))->assertRedirect(route('login'));
    }
}
