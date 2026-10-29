<?php

namespace Tests\Feature\Classes;

use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\StudySchedule;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Models\UserOnboarding;
use App\Services\Admin\AnalyticsService;
use App\Services\Admin\ImpersonationService;
use App\Services\Auth\AccountDeletionService;
use App\Services\Auth\DataExportService;
use App\Services\OnboardingService;
use App\Services\Parenting\ChildLinkService;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;

/** Hướng dẫn người mới (OB-01 → OB-04, OB-06). */
class OnboardingTest extends ClassroomTestCase
{
    private function service(): OnboardingService
    {
        return app(OnboardingService::class);
    }

    private function makeParent(): User
    {
        $p = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $p->assignRole(Role::PARENT);
        ParentProfile::create(['user_id' => $p->id, 'weekly_report_enabled' => true]);

        return $p;
    }

    private function done(User $u, string $key): bool
    {
        return collect($this->service()->checklist($u->fresh())['items'])->firstWhere('key', $key)['done'];
    }

    // --- Cấu hình: gõ sai là test bắt ngay (như GuideTest) ------------------------------------

    public function test_config_routes_keys_and_tour_targets_all_exist(): void
    {
        $known = $this->service()->knownKeys();

        foreach (config('onboarding.checklists') as $role => $items) {
            foreach ($items as $item) {
                $this->assertTrue(Route::has($item['route']), "Route {$item['route']} ({$role}) không tồn tại.");
                $this->assertContains($item['key'], $known, "Mục {$item['key']} chưa có điều kiện trong OnboardingService.");
            }
        }

        // Mọi data-tour khai trong config phải được gắn ở view hoặc menu — đổi giao diện mà xoá mất là test đỏ.
        $haystack = collect(File::allFiles(resource_path('views')))->map(fn ($f) => $f->getContents())->implode("\n")
            .File::get(config_path('navigation.php'));

        foreach (config('onboarding.tours') as $route => $steps) {
            $this->assertTrue(Route::has($route), "Tour gắn vào route không tồn tại: {$route}");
            foreach ($steps as $step) {
                $this->assertTrue(
                    str_contains($haystack, 'data-tour="'.$step['target'].'"') || str_contains($haystack, "'tour' => '{$step['target']}'"),
                    "Không thấy data-tour=\"{$step['target']}\" ở đâu cả (tour {$route}).",
                );
            }
        }
    }

    // --- Danh sách "Bắt đầu" tự tích theo dữ liệu thật ------------------------------------------

    public function test_student_checklist_ticks_itself_from_real_data(): void
    {
        $student = $this->makeStudent();
        $this->assertSame(0, $this->service()->checklist($student)['done']);

        StudySchedule::create(['student_id' => $student->id, 'weekday' => 1, 'start_time' => '19:00', 'duration_minutes' => 45]);
        $this->assertTrue($this->done($student, 'schedule'));

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Bắt đầu với Math AI')
            ->assertSee('1/5 việc');
    }

    public function test_parent_checklist_counts_link_schedule_and_first_visit(): void
    {
        $parent = $this->makeParent();
        $student = $this->makeStudent();
        app(ChildLinkService::class)->linkByCode($parent, $student->studentProfile()->value('link_code'));
        StudySchedule::create(['student_id' => $student->id, 'weekday' => 2, 'start_time' => '20:00', 'duration_minutes' => 60]);

        $this->assertTrue($this->done($parent, 'link_child'));
        $this->assertTrue($this->done($parent, 'child_schedule'));
        $this->assertFalse($this->done($parent, 'view_child'));

        $this->actingAs($parent)->get(route('parent.children.show', $student))->assertOk();
        $this->assertTrue($this->done($parent, 'view_child'));
    }

    public function test_teacher_checklist_follows_class_and_profile(): void
    {
        $teacher = $this->teacher;
        TeacherProfile::updateOrCreate(['user_id' => $teacher->id], ['school' => 'THCS A', 'subject' => 'Toán', 'approved_at' => now()]);
        $class = $this->makeClass();
        $this->enroll($class, $this->makeStudent());

        $this->assertTrue($this->done($teacher, 'teacher_profile'));
        $this->assertTrue($this->done($teacher, 'create_class'));
        $this->assertTrue($this->done($teacher, 'class_students'));
        $this->assertFalse($this->done($teacher, 'assignment'));
    }

    public function test_hiding_the_checklist_is_remembered_server_side(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->postJson(route('api.onboarding'), ['key' => 'checklist:hidden', 'status' => 'hidden'])->assertNoContent();

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->assertDontSee('Bắt đầu với Math AI');
    }

    // --- Tour: chỉ mời lần đầu, lưu ở server --------------------------------------------------------

    public function test_first_visit_invites_then_stops_after_dismiss_or_completion(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('id="onboarding-tour"', false)
            ->assertSee('"invite":true', false)
            ->assertSee('Hướng dẫn trang này');

        $this->actingAs($student)->postJson(route('api.onboarding'), ['key' => 'tour:student.dashboard', 'status' => 'dismissed', 'step' => 2])
            ->assertNoContent();

        $this->actingAs($student)->get(route('student.dashboard'))->assertSee('"invite":false', false);
        $this->assertSame(2, UserOnboarding::where('key', 'tour:student.dashboard')->value('step'));
    }

    public function test_completed_tour_is_not_downgraded_by_a_later_skip(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->postJson(route('api.onboarding'), ['key' => 'tour:student.dashboard', 'status' => 'completed']);
        $this->actingAs($student)->postJson(route('api.onboarding'), ['key' => 'tour:student.dashboard', 'status' => 'dismissed', 'step' => 1]);

        $this->assertSame('completed', UserOnboarding::where('user_id', $student->id)->value('status'));
    }

    public function test_unknown_keys_are_rejected_and_impersonation_is_not_recorded(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->postJson(route('api.onboarding'), ['key' => 'tour:hacker', 'status' => 'completed'])->assertUnprocessable();

        $this->actingAs($student)->withSession([ImpersonationService::SESSION_KEY => 1])
            ->postJson(route('api.onboarding'), ['key' => 'tour:student.dashboard', 'status' => 'completed'])->assertNoContent();

        $this->assertDatabaseCount('user_onboarding', 0);
    }

    public function test_pages_without_a_tour_render_no_tour_data(): void
    {
        $this->actingAs($this->makeStudent())->get(route('student.settings'))->assertOk()->assertDontSee('id="onboarding-tour"', false);
    }

    // --- Dữ liệu cá nhân + đo lường ---------------------------------------------------------------

    public function test_admin_dashboard_shows_onboarding_effectiveness(): void
    {
        $this->service()->record($this->makeStudent(), 'tour:student.dashboard', 'completed');
        app(AnalyticsService::class)->forget();

        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole(Role::ADMIN);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('Hướng dẫn người mới')
            ->assertSee('student.dashboard');
    }

    public function test_progress_is_exported_wiped_and_counted(): void
    {
        $student = $this->makeStudent();
        $this->service()->record($student, 'tour:student.dashboard', 'dismissed', 3);

        $this->assertCount(1, app(DataExportService::class)->build($student)['onboarding']);

        $stats = $this->service()->stats();
        $this->assertSame(1, $stats['tours']['tour:student.dashboard']['dismissed']);
        $this->assertSame(3, $stats['tours']['tour:student.dashboard']['top_drop_step']);

        app(AccountDeletionService::class)->request($student);
        User::withTrashed()->whereKey($student->id)->update(['deleted_at' => now()->subDays(AccountDeletionService::GRACE_DAYS + 1)]);
        $this->artisan('accounts:purge')->assertSuccessful();

        $this->assertDatabaseCount('user_onboarding', 0);
    }
}
