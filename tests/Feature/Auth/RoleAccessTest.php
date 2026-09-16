<?php

namespace Tests\Feature\Auth;

use App\Models\Role;
use App\Models\TeacherProfile;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RoleAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole($role);

        return $user;
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('student.dashboard'))->assertRedirect(route('login'));
    }

    public function test_student_cannot_open_teacher_portal(): void
    {
        $this->actingAs($this->userWithRole(Role::STUDENT))
            ->get(route('teacher.dashboard'))
            ->assertForbidden();
    }

    public function test_teacher_cannot_open_admin_portal(): void
    {
        $this->actingAs($this->userWithRole(Role::TEACHER))
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_each_role_reaches_its_own_dashboard(): void
    {
        $cases = [
            Role::STUDENT => 'student.dashboard',
            Role::TEACHER => 'teacher.dashboard',
            Role::PARENT => 'parent.dashboard',
            Role::ADMIN => 'admin.dashboard',
        ];

        foreach ($cases as $role => $route) {
            $this->actingAs($this->userWithRole($role))
                ->get(route($route))
                ->assertOk();
        }
    }

    public function test_admin_bypasses_permission_gates(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        // Admin không được gán trực tiếp nhưng Gate::before cho qua.
        $this->assertTrue($admin->can('lesson.publish'));
        $this->assertFalse($this->userWithRole(Role::STUDENT)->can('lesson.publish'));
    }

    public function test_admin_approves_pending_teacher(): void
    {
        $admin = $this->userWithRole(Role::ADMIN);

        $teacher = User::factory()->create(['status' => User::STATUS_PENDING]);
        $teacher->assignRole(Role::TEACHER);
        TeacherProfile::create(['user_id' => $teacher->id, 'school' => 'THCS Test']);

        $this->actingAs($admin)
            ->post(route('admin.teachers.approve', $teacher))
            ->assertRedirect();

        $teacher->refresh()->load('teacherProfile');
        $this->assertSame(User::STATUS_ACTIVE, $teacher->status);
        $this->assertNotNull($teacher->teacherProfile->approved_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'teacher.approved']);

        // Sau khi duyệt thì vào được portal.
        $this->actingAs($teacher)->get(route('teacher.dashboard'))->assertOk();
    }
}
