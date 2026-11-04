<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Support\TeacherPortal;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Công tắc tạm ẩn chức năng giáo viên. phpunit.xml để BẬT cho các test khác của cổng giáo viên,
 * nên ở đây tự tắt; test cuối cùng canh bật lại là mọi thứ về như cũ.
 */
class TeacherPortalHiddenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
        config(['site.teacher_portal' => false]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['password' => 'secret-Pass1']);
        $user->assignRole($role);

        return $user;
    }

    public function test_teacher_cannot_log_in(): void
    {
        $teacher = $this->user('teacher');

        $this->from(route('login'))
            ->post(route('login'), ['email' => $teacher->email, 'password' => 'secret-Pass1'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => TeacherPortal::CLOSED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_wrong_password_does_not_reveal_that_the_email_is_a_teacher(): void
    {
        $teacher = $this->user('teacher');

        $this->from(route('login'))
            ->post(route('login'), ['email' => $teacher->email, 'password' => 'sai-mat-khau'])
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
    }

    public function test_teacher_already_logged_in_is_pushed_out(): void
    {
        $this->actingAs($this->user('teacher'))
            ->get(route('teacher.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => TeacherPortal::CLOSED_MESSAGE]);

        $this->assertGuest();
    }

    public function test_other_roles_are_untouched(): void
    {
        foreach (['student', 'parent'] as $role) {
            $user = $this->user($role);

            $this->post(route('login'), ['email' => $user->email, 'password' => 'secret-Pass1'])
                ->assertRedirect($user->homeRoute());
            $this->assertAuthenticatedAs($user);

            $this->post(route('logout'));
        }
    }

    public function test_admin_can_still_use_the_authoring_pages(): void
    {
        $this->actingAs($this->user('admin'))
            ->get(route('teacher.lessons.index'))
            ->assertOk();
    }

    public function test_registration_is_closed_and_not_linked(): void
    {
        $this->get(route('register.teacher'))->assertNotFound();
        $this->post(route('register.teacher'), [])->assertNotFound();

        $this->get(route('register'))->assertOk()
            ->assertDontSee('dang-ky/giao-vien', false)
            ->assertDontSee('Giáo viên')
            ->assertSee(route('register.student'), false)
            ->assertSee(route('register.parent'), false);

        // Footer các trang công khai cũng không còn link.
        $this->get(route('legal.terms'))->assertOk()->assertDontSee('dang-ky/giao-vien', false);
    }

    public function test_teacher_guides_disappear_everywhere(): void
    {
        $slug = collect(config('guides.articles'))->where('audience', 'teacher')->keys()->first();
        $url = route('guides.show', $slug);

        $this->get($url)->assertNotFound();
        $this->get(route('guides.index'))->assertOk()->assertDontSee($url, false);
        $this->get(route('sitemap'))->assertDontSee($url, false);
        $this->get('/llms.txt')->assertDontSee($url, false)->assertDontSee('giáo viên (lớp học');
    }

    public function test_admin_cannot_impersonate_a_teacher_while_hidden(): void
    {
        $admin = $this->user('admin');
        $teacher = $this->user('teacher');

        $this->actingAs($admin)->post(route('admin.users.impersonate', $teacher));

        $this->assertAuthenticatedAs($admin);
    }

    public function test_turning_the_switch_back_on_restores_everything(): void
    {
        config(['site.teacher_portal' => true]);
        $teacher = $this->user('teacher');

        $this->get(route('register.teacher'))->assertOk();
        $this->post(route('login'), ['email' => $teacher->email, 'password' => 'secret-Pass1'])
            ->assertRedirect($teacher->homeRoute());
        $this->assertAuthenticatedAs($teacher);
    }
}
