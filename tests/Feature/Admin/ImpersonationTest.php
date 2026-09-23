<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $role): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['password' => 'secret-Pass1']);
        $user->assignRole($role);

        return $user;
    }

    public function test_admin_can_impersonate_and_return_with_audit_trail(): void
    {
        $admin = $this->user('admin');
        $student = $this->user('student');

        $this->actingAs($admin)->post(route('admin.users.impersonate', $student))->assertRedirect();
        $this->assertAuthenticatedAs($student);
        $this->get(route('student.dashboard'))->assertOk()->assertSee('Đang đăng nhập hộ');

        $this->post(route('impersonate.stop'))->assertRedirect(route('admin.users.show', $student));
        $this->assertAuthenticatedAs($admin);

        $this->assertSame(['impersonation.started', 'impersonation.stopped'],
            AuditLog::orderBy('id')->pluck('action')->all());
        // Cả hai dòng đều ghi tên admin là người thao tác, không phải học sinh.
        $this->assertSame([$admin->id, $student->id], [AuditLog::first()->user_id, AuditLog::latest('id')->first()->auditable_id]);
    }

    public function test_cannot_impersonate_admin_or_self(): void
    {
        $admin = $this->user('admin');
        $other = $this->user('admin');

        $this->actingAs($admin)->post(route('admin.users.impersonate', $other))->assertSessionHas('error');
        $this->post(route('admin.users.impersonate', $admin))->assertSessionHas('error');
        $this->assertAuthenticatedAs($admin);
    }

    public function test_non_admin_cannot_start_impersonation(): void
    {
        $student = $this->user('student');
        $other = $this->user('student');

        $this->actingAs($student)->post(route('admin.users.impersonate', $other))->assertForbidden();
    }

    public function test_sensitive_actions_are_blocked_while_impersonating(): void
    {
        $admin = $this->user('admin');
        $student = $this->user('student');

        $this->actingAs($admin)->post(route('admin.users.impersonate', $student));

        $this->put(route('student.settings.password'), [
            'current_password' => 'secret-Pass1', 'password' => 'Newpass123', 'password_confirmation' => 'Newpass123',
        ])->assertSessionHas('error');

        $this->assertTrue(Hash::check('secret-Pass1', $student->refresh()->password));
    }

    public function test_stop_without_impersonating_does_nothing_harmful(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)->post(route('impersonate.stop'))->assertRedirect(route('login'));
    }
}
