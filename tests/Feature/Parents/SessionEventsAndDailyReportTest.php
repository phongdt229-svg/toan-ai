<?php

namespace Tests\Feature\Parents;

use App\Models\StudentAttendance;
use App\Models\StudentDailyActivity;
use App\Models\StudySchedule;
use App\Models\User;
use App\Notifications\ChildAttendanceAlert;
use App\Notifications\DailyChildReport;
use App\Services\Learning\AttendanceService;
use Illuminate\Support\Facades\Notification;

/** TA-13: báo bắt đầu / xong buổi (tự bật) + báo cáo cuối ngày (tự bật). */
class SessionEventsAndDailyReportTest extends ParentTestCase
{
    private User $parent;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->parent = $this->makeParent();
        $this->student = $this->makeStudent('Bé Na');
        $this->link($this->parent, $this->student);

        $this->travelTo(today()->setTime(18, 0));
        StudySchedule::create([
            'student_id' => $this->student->id, 'weekday' => today()->isoWeekday(),
            'start_time' => '19:00', 'duration_minutes' => 60,
        ]);
    }

    private function at(int $h, int $m, int $studiedMinutes = 0): void
    {
        $this->travelTo(today()->setTime($h, $m));
        if ($studiedMinutes) {
            StudentDailyActivity::updateOrCreate(
                ['user_id' => $this->student->id, 'activity_date' => today()->toDateString()],
                ['online_seconds' => $studiedMinutes * 60, 'active_seconds' => $studiedMinutes * 60, 'last_active_at' => now()],
            );
        }
        app(AttendanceService::class)->tick();
    }

    private function runSlot(): void
    {
        $this->at(19, 0);
        $this->at(19, 3, studiedMinutes: 2);
        $this->at(20, 0, studiedMinutes: 50);
    }

    public function test_start_and_finish_are_silent_by_default(): void
    {
        $this->runSlot();

        Notification::assertNotSentTo($this->parent, ChildAttendanceAlert::class);
    }

    public function test_opted_in_parent_hears_start_and_finish(): void
    {
        $this->actingAs($this->parent)->put(route('parent.settings.update'), [
            'weekly_report_enabled' => '1', 'session_events_enabled' => '1',
        ])->assertSessionHas('status');

        $this->runSlot();

        Notification::assertSentTo($this->parent, ChildAttendanceAlert::class,
            fn (ChildAttendanceAlert $n) => $n->kind === ChildAttendanceAlert::STARTED && $n->attendance->late_minutes === 3);
        Notification::assertSentTo($this->parent, ChildAttendanceAlert::class,
            fn (ChildAttendanceAlert $n) => $n->kind === ChildAttendanceAlert::FINISHED
                && str_contains($n->toArray($this->parent)['message'], 'Có mặt'));
    }

    public function test_daily_report_goes_only_to_opted_in_parents_once_a_day(): void
    {
        $this->parent->parentProfile()->update(['daily_report_enabled' => true]);
        $other = $this->makeParent(); // không bật
        $this->link($other, $this->student);

        $this->runSlot();
        $this->travelTo(today()->setTime(21, 30));
        $this->artisan('reports:daily-parents')->assertSuccessful();
        $this->artisan('reports:daily-parents')->assertSuccessful();

        Notification::assertSentToTimes($this->parent, DailyChildReport::class, 1);
        Notification::assertNotSentTo($other, DailyChildReport::class);
        Notification::assertSentTo($this->parent, DailyChildReport::class, function (DailyChildReport $n) {
            $line = $n->lines[0];

            return $line['name'] === 'Bé Na' && $line['active'] === 50 && $line['attendance'] === StudentAttendance::LABELS['present']
                && in_array('mail', $n->via($this->parent), true);
        });
    }

    public function test_no_report_on_a_day_without_schedule_or_study(): void
    {
        $this->parent->parentProfile()->update(['daily_report_enabled' => true]);
        StudySchedule::query()->delete();

        $this->travelTo(today()->setTime(21, 30));
        $this->artisan('reports:daily-parents')->assertSuccessful();

        Notification::assertNotSentTo($this->parent, DailyChildReport::class);
    }

    public function test_settings_switches_save_and_default_off(): void
    {
        $profile = $this->parent->parentProfile()->first();
        $this->assertFalse($profile->session_events_enabled);
        $this->assertFalse($profile->daily_report_enabled);

        $this->actingAs($this->parent)->put(route('parent.settings.update'), ['daily_report_enabled' => '1']);

        $profile->refresh();
        $this->assertTrue($profile->daily_report_enabled);
        $this->assertFalse($profile->weekly_report_enabled, 'Công tắc bỏ chọn = tắt.');

        $this->actingAs($this->student)->put(route('parent.settings.update'), ['daily_report_enabled' => '1'])->assertForbidden();
    }
}
