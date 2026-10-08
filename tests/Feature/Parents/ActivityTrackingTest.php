<?php

namespace Tests\Feature\Parents;

use App\Models\Role;
use App\Models\StudentActivityLog;
use App\Models\StudentDailyActivity;
use App\Models\User;
use App\Services\Admin\ImpersonationService;
use App\Services\Auth\AccountDeletionService;
use App\Services\Auth\DataExportService;
use App\Services\Learning\ActivityService;

/** Thời gian học thật (đặc tả "Logic" — active learning time): heartbeat → online / học thực theo ngày. */
class ActivityTrackingTest extends ParentTestCase
{
    private function beat(User $student, bool $active = true, array $events = []): void
    {
        $this->actingAs($student)
            ->postJson(route('api.activity'), ['active' => $active, 'events' => $events])
            ->assertNoContent();
    }

    private function today(User $student): StudentDailyActivity
    {
        return StudentDailyActivity::where('user_id', $student->id)->firstOrFail();
    }

    public function test_heartbeats_add_up_by_real_elapsed_time(): void
    {
        $student = $this->makeStudent();

        $this->beat($student);               // mốc đầu ngày: chưa cộng
        $this->travel(30)->seconds();
        $this->beat($student);
        $this->travel(30)->seconds();
        $this->beat($student, active: false); // tab mở nhưng không thao tác

        $row = $this->today($student);
        $this->assertSame(60, $row->online_seconds);
        $this->assertSame(30, $row->active_seconds);
    }

    public function test_bursts_and_long_gaps_cannot_inflate_time(): void
    {
        $student = $this->makeStudent();
        $this->beat($student);

        // Gửi dồn (tab thứ hai, script) — sát nhau dưới 5 giây thì bỏ qua.
        $this->travel(2)->seconds();
        $this->beat($student);

        // Mất mạng 10 phút rồi quay lại — chỉ được cộng tối đa một nhịp.
        $this->travel(10)->minutes();
        $this->beat($student);

        $this->assertSame(ActivityService::MAX_CREDIT_SECONDS, $this->today($student)->online_seconds);
    }

    public function test_ui_events_are_logged_and_unknown_types_rejected(): void
    {
        $student = $this->makeStudent();

        $this->beat($student, events: [
            ['type' => 'lesson_open', 'path' => '/hoc-sinh/bai-hoc/phan-so'],
            ['type' => 'section_view', 'meta' => ['section_id' => 3]],
        ]);

        $this->assertSame(['lesson_open', 'section_view'], StudentActivityLog::orderBy('id')->pluck('event_type')->all());

        $this->actingAs($student)
            ->postJson(route('api.activity'), ['active' => true, 'events' => [['type' => 'drop_table']]])
            ->assertUnprocessable();
    }

    public function test_only_students_can_report_activity(): void
    {
        $this->postJson(route('api.activity'), ['active' => true])->assertUnauthorized();

        $this->actingAs($this->makeParent())
            ->postJson(route('api.activity'), ['active' => true])
            ->assertForbidden();
    }

    public function test_support_staff_impersonating_does_not_add_study_time(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)
            ->withSession([ImpersonationService::SESSION_KEY => 1])
            ->postJson(route('api.activity'), ['active' => true])
            ->assertNoContent();

        $this->assertDatabaseCount('student_daily_activity', 0);
    }

    public function test_parent_sees_online_vs_active_time_for_the_week(): void
    {
        $parent = $this->makeParent();
        $student = $this->makeStudent();
        $this->link($parent, $student);

        StudentDailyActivity::create([
            'user_id' => $student->id, 'activity_date' => today(),
            'online_seconds' => 90 * 60, 'active_seconds' => 8 * 60,
        ]);

        $this->actingAs($parent)->get(route('parent.children.show', $student))
            ->assertOk()
            ->assertSee('Thời gian học 7 ngày qua')
            ->assertSee('8 phút')
            ->assertSee('90 phút')
            ->assertSee('Tập trung thấp');
    }

    public function test_student_dashboard_shows_today(): void
    {
        $student = $this->makeStudent();
        StudentDailyActivity::create([
            'user_id' => $student->id, 'activity_date' => today(),
            'online_seconds' => 31 * 60, 'active_seconds' => 25 * 60,
        ]);

        $this->actingAs($student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('25 phút');
    }

    public function test_activity_is_exported_and_wiped_with_the_account(): void
    {
        $student = $this->makeStudent();
        $this->beat($student, events: [['type' => 'lesson_open']]);

        $export = app(DataExportService::class)->build($student);
        $this->assertCount(1, $export['learning']['daily_activity']);
        $this->assertCount(1, $export['learning']['activity_logs']);

        app(AccountDeletionService::class)->request($student);
        User::withTrashed()->whereKey($student->id)
            ->update(['deleted_at' => now()->subDays(AccountDeletionService::GRACE_DAYS + 1)]);
        $this->artisan('accounts:purge')->assertSuccessful();

        $this->assertDatabaseCount('student_daily_activity', 0);
        $this->assertDatabaseCount('student_activity_logs', 0);
    }

    public function test_focus_level_needs_enough_online_time(): void
    {
        $service = app(ActivityService::class);

        $this->assertNull($service->focusLevel(120, 120));
        $this->assertSame('high', $service->focusLevel(600, 400));
        $this->assertSame('medium', $service->focusLevel(600, 200));
        $this->assertSame('low', $service->focusLevel(600, 60));
        $this->assertTrue(Role::where('name', Role::STUDENT)->exists());
    }
}
