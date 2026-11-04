<?php

namespace Tests\Feature\Auth;

use App\Jobs\SendWeeklyParentReport;
use App\Mail\WeeklyParentReport;
use App\Models\User;
use App\Notifications\DailyChildReport;
use App\Support\AiTutorFaq;
use App\Support\RolePortal;
use Database\Seeders\GradeSeeder;
use Database\Seeders\PackageSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\Events\NotificationSending;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Công tắc tạm ẩn cổng theo vai trò. phpunit.xml để BẬT cả hai cho các test khác của từng cổng,
 * nên ở đây tự đặt đúng trạng thái production: giáo viên mở, phụ huynh ẩn.
 */
class RolePortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
        config(['site.portals' => ['teacher' => true, 'parent' => false]]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create(['password' => 'secret-Pass1']);
        $user->assignRole($role);

        return $user;
    }

    public function test_the_shipped_default_is_teacher_open_and_parent_hidden(): void
    {
        $source = file_get_contents(config_path('site.php'));

        $this->assertStringContainsString("env('TEACHER_PORTAL_ENABLED', true)", $source);
        $this->assertStringContainsString("env('PARENT_PORTAL_ENABLED', false)", $source);
    }

    public function test_parent_cannot_log_in_but_teacher_can(): void
    {
        $parent = $this->user('parent');

        $this->from(route('login'))
            ->post(route('login'), ['email' => $parent->email, 'password' => 'secret-Pass1'])
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors(['email' => 'Chức năng phụ huynh đang tạm đóng. Vui lòng quay lại sau hoặc gửi yêu cầu hỗ trợ.']);
        $this->assertGuest();

        $teacher = $this->user('teacher');
        $this->post(route('login'), ['email' => $teacher->email, 'password' => 'secret-Pass1'])
            ->assertRedirect($teacher->homeRoute());
        $this->assertAuthenticatedAs($teacher);
    }

    public function test_wrong_password_does_not_reveal_the_role(): void
    {
        $this->from(route('login'))
            ->post(route('login'), ['email' => $this->user('parent')->email, 'password' => 'sai-mat-khau'])
            ->assertSessionHasErrors(['email' => 'Email hoặc mật khẩu không đúng.']);
    }

    public function test_parent_already_logged_in_is_pushed_out(): void
    {
        $this->actingAs($this->user('parent'))
            ->get(route('parent.dashboard'))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_student_still_logs_in(): void
    {
        $student = $this->user('student');

        $this->post(route('login'), ['email' => $student->email, 'password' => 'secret-Pass1'])
            ->assertRedirect($student->homeRoute());
        $this->assertAuthenticatedAs($student);
    }

    public function test_parent_registration_is_closed_and_teacher_is_open(): void
    {
        $this->get(route('register.parent'))->assertNotFound();
        $this->post(route('register.parent'), [])->assertNotFound();
        $this->get(route('register.teacher'))->assertOk();

        $this->get(route('register'))->assertOk()
            ->assertDontSee('dang-ky/phu-huynh', false)
            ->assertSee(route('register.student'), false)
            ->assertSee(route('register.teacher'), false);

        $this->get(route('legal.terms'))->assertOk()
            ->assertDontSee('dang-ky/phu-huynh', false)
            ->assertSee('dang-ky/giao-vien', false);
    }

    public function test_home_signs_up_through_the_student_account_and_drops_parent_promises(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('<form action="'.route('register.student').'"', $html);
        $this->assertStringNotContainsString('dang-ky/phu-huynh', $html);
        foreach (['id="phu-huynh"', 'href="#phu-huynh"', 'Bố mẹ theo dõi được', 'Báo cáo cho bố mẹ', 'tạo tài khoản phụ huynh', 'báo cáo dành cho phụ huynh'] as $promise) {
            $this->assertStringNotContainsString($promise, $html, $promise);
        }
    }

    public function test_home_is_unchanged_when_the_parent_portal_is_open(): void
    {
        config(['site.portals.parent' => true]);

        $this->get(route('home'))->assertOk()
            ->assertSee('<form action="'.route('register.parent').'"', false)
            ->assertSee('id="phu-huynh"', false)
            ->assertSee('Bố mẹ theo dõi được');
    }

    public function test_student_no_longer_sees_the_parent_section(): void
    {
        $student = $this->user('student');

        $this->actingAs($student)->get(route('student.parents.index'))->assertNotFound();
        $this->actingAs($student)->post(route('student.parents.regenerate'))->assertNotFound();
        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()
            ->assertDontSee(route('student.parents.index'), false)
            ->assertDontSee('Liên kết với phụ huynh');
    }

    public function test_parent_guides_disappear_but_teacher_guides_stay(): void
    {
        $parentGuide = route('guides.show', collect(config('guides.articles'))->where('audience', 'parent')->keys()->first());
        $linkGuide = route('guides.show', 'lien-ket-voi-phu-huynh');
        $teacherGuide = route('guides.show', collect(config('guides.articles'))->where('audience', 'teacher')->keys()->first());

        foreach ([$parentGuide, $linkGuide] as $url) {
            $this->get($url)->assertNotFound();
            $this->get(route('guides.index'))->assertDontSee($url, false);
            $this->get(route('sitemap'))->assertDontSee($url, false);
            $this->get('/llms.txt')->assertDontSee($url, false);
        }

        $this->get($teacherGuide)->assertOk();
        $this->get('/llms.txt')->assertSee($teacherGuide, false)->assertDontSee('nhận báo cáo tuần');
    }

    public function test_pricing_does_not_sell_parent_reports(): void
    {
        $this->seed(PackageSeeder::class);

        $this->get(route('packages.index'))->assertOk()
            ->assertDontSee('Báo cáo nâng cao cho phụ huynh')
            ->assertDontSee('Phụ huynh có thể mua gói');
    }

    public function test_ai_page_drops_parent_promises(): void
    {
        $this->assertSame([], array_filter(AiTutorFaq::items(), fn ($faq) => str_starts_with($faq[0], 'Phụ huynh')));

        $this->get(route('ai-learning'))->assertOk()
            ->assertDontSee('Phụ huynh theo dõi tiến độ')
            ->assertDontSee('hiện cho cả phụ huynh xem');
    }

    public function test_tracking_notifications_are_dropped_but_others_still_go_out(): void
    {
        $parent = $this->user('parent');

        $tracking = new NotificationSending($parent, new DailyChildReport(now(), []), 'mail');
        $this->assertFalse(event($tracking, halt: true));

        // Thông báo khác không thuộc nhóm theo dõi: không bị chặn.
        $other = new NotificationSending($parent, new ResetPassword('token'), 'mail');
        $this->assertNotFalse(event($other, halt: true));
    }

    public function test_weekly_report_job_sends_nothing_while_hidden(): void
    {
        Mail::fake();
        $parent = $this->user('parent');
        $parent->parentProfile()->updateOrCreate([], ['weekly_report_enabled' => true]);

        SendWeeklyParentReport::dispatchSync($parent->id);

        Mail::assertNothingSent();
        Mail::assertNotQueued(WeeklyParentReport::class);
    }

    public function test_admin_cannot_impersonate_a_hidden_role_but_can_a_teacher(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('admin.users.impersonate', $this->user('parent')));
        $this->assertAuthenticatedAs($admin);

        $teacher = $this->user('teacher');
        $this->actingAs($admin)->post(route('admin.users.impersonate', $teacher));
        $this->assertAuthenticatedAs($teacher);
    }

    public function test_either_portal_can_be_switched(): void
    {
        config(['site.portals' => ['teacher' => false, 'parent' => true]]);
        $teacher = $this->user('teacher');

        $this->assertTrue(RolePortal::blocks($teacher));
        $this->assertFalse(RolePortal::blocks($this->user('parent')));
        $this->assertFalse(RolePortal::blocks($this->user('admin')));
        $this->get(route('register.teacher'))->assertNotFound();
        $this->get(route('register.parent'))->assertOk();
    }
}
