<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Trang chủ: đã đăng nhập thì header đưa thẳng vào cổng của vai trò thay vì nút Đăng nhập/Đăng ký. */
class LandingPortalLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Nguyễn Văn Thử']);
        $user->assignRole($role);

        return $user;
    }

    public function test_guest_sees_login_and_signup(): void
    {
        $this->get(route('home'))->assertOk()
            ->assertSee('href="'.route('login').'"', false)
            ->assertSee('Đăng ký cho con →')
            ->assertDontSee('data-portal-link', false);
    }

    public function test_admin_gets_a_link_to_the_admin_dashboard(): void
    {
        $this->actingAs($this->userWithRole(Role::ADMIN))->get(route('home'))->assertOk()
            ->assertSee('href="'.route('admin.dashboard').'" data-portal-link', false)
            ->assertSee('Vào trang quản trị')
            ->assertSee('Nguyễn Văn Thử')
            ->assertDontSee('>Đăng nhập</a>', false)
            ->assertHeader('Cache-Control', 'no-store, private'); // CDN không được cache bản có tên người dùng
    }

    public function test_student_gets_a_link_to_the_learning_dashboard(): void
    {
        $this->actingAs($this->userWithRole(Role::STUDENT))->get(route('home'))->assertOk()
            ->assertSee('href="'.route('student.dashboard').'" data-portal-link', false)
            ->assertSee('Vào học ngay')
            ->assertDontSee('Học sinh đăng nhập');
    }

    public function test_teacher_gets_a_link_to_the_teacher_dashboard(): void
    {
        $this->actingAs($this->userWithRole(Role::TEACHER))->get(route('home'))->assertOk()
            ->assertSee('href="'.route('teacher.dashboard').'" data-portal-link', false)
            ->assertSee('Vào trang giáo viên');
    }

    public function test_parent_with_closed_portal_is_treated_as_guest(): void
    {
        config(['site.portals.parent' => false]);

        $this->actingAs($this->userWithRole(Role::PARENT))->get(route('home'))->assertOk()
            ->assertDontSee('data-portal-link', false)
            ->assertSee('href="'.route('login').'"', false);
    }
}
