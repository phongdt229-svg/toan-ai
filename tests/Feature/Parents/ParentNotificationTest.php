<?php

namespace Tests\Feature\Parents;

use App\Mail\WeeklyParentReport;
use App\Models\LearningPath;
use App\Models\StudySchedule;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use App\Notifications\ChildScheduleReminder;
use App\Notifications\ChildScoreLow;
use App\Notifications\WeeklyReportReady;
use App\Services\Learning\StudentReportService;
use App\Support\NotificationType;
use Illuminate\Support\Facades\Notification;

/** Thông báo cho phụ huynh (đặc tả module 9): nhắc lịch học, số buổi đã học/còn lại, kênh đẩy. */
class ParentNotificationTest extends ParentTestCase
{
    private function slot(User $student, int $weekday, string $start = '19:30', int $minutes = 60): void
    {
        StudySchedule::create([
            'student_id' => $student->id, 'weekday' => $weekday, 'start_time' => $start,
            'duration_minutes' => $minutes, 'updated_by' => $student->id,
        ]);
    }

    private function path(User $student, int $done, int $total): LearningPath
    {
        return LearningPath::create([
            'user_id' => $student->id, 'grade_id' => $this->grade->id, 'status' => LearningPath::STATUS_ACTIVE,
            'items_per_session' => 3, 'total_sessions' => $total, 'completed_sessions' => $done, 'generated_at' => now(),
        ]);
    }

    // --- Nhắc lịch học ---------------------------------------------------------------------

    public function test_parent_is_reminded_of_todays_slots_once_a_day(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent('Bé Nam');
        $this->link($parent, $child);
        $this->slot($child, today()->dayOfWeekIso, '19:30', 60);
        $this->slot($child, today()->addDay()->dayOfWeekIso, '08:00'); // ngày mai — không nhắc hôm nay

        $this->artisan('parents:remind-schedule')->assertSuccessful();
        $this->artisan('parents:remind-schedule')->assertSuccessful(); // chạy lại trong ngày không nhắc lần hai

        $notes = $parent->notifications()->where('type', ChildScheduleReminder::class)->get();
        $this->assertCount(1, $notes);
        $this->assertStringContainsString('Bé Nam học 19:30 (60 phút)', $notes[0]->data['message']);
        $this->assertStringNotContainsString('08:00', $notes[0]->data['message']);
    }

    public function test_no_reminder_when_no_child_has_a_slot_today(): void
    {
        Notification::fake();
        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $this->link($parent, $child);
        $this->slot($child, today()->addDay()->dayOfWeekIso);

        $this->artisan('parents:remind-schedule')->assertSuccessful();

        Notification::assertNotSentTo($parent, ChildScheduleReminder::class);
    }

    public function test_one_reminder_groups_all_children_and_respects_muting(): void
    {
        $parent = $this->makeParent();
        $a = $this->makeStudent('Con A');
        $b = $this->makeStudent('Con B');
        $this->link($parent, $a);
        $this->link($parent, $b);
        $this->slot($a, today()->dayOfWeekIso, '18:00');
        $this->slot($b, today()->dayOfWeekIso, '20:00');

        $this->artisan('parents:remind-schedule')->assertSuccessful();

        $note = $parent->notifications()->where('type', ChildScheduleReminder::class)->sole();
        $this->assertStringContainsString('Con A học 18:00', $note->data['message']);
        $this->assertStringContainsString('Con B học 20:00', $note->data['message']);

        // Loại này có trong Cài đặt để phụ huynh tắt.
        $this->assertArrayHasKey(ChildScheduleReminder::class, NotificationType::LABELS);
    }

    public function test_reminder_goes_to_the_chime_and_push_but_not_email(): void
    {
        $channels = (new ChildScheduleReminder([]))->via($this->makeParent());

        $this->assertSame(['database', WebPushChannel::class], $channels);
    }

    // --- Số buổi đã học / còn lại ----------------------------------------------------------

    public function test_session_progress_reports_done_remaining_and_percent(): void
    {
        $child = $this->makeStudent();
        $this->path($child, 3, 12);

        $progress = app(StudentReportService::class)->sessionProgress($child);

        $this->assertSame(['done' => 3, 'total' => 12, 'remaining' => 9, 'percent' => 25], $progress);
        $this->assertNull(app(StudentReportService::class)->sessionProgress($this->makeStudent())); // chưa có lộ trình → không bịa số
    }

    public function test_daily_and_weekly_reports_show_remaining_sessions(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent('Bé Hoa');
        $this->link($parent, $child);
        $this->path($child, 4, 10);

        $weekly = app(StudentReportService::class)->weekly($child, now()->subWeek(), now());
        $this->assertSame(6, $weekly['sessions']['remaining']);

        $html = (new WeeklyParentReport($parent, [$weekly], now()->subWeek(), now()))->render();
        $this->assertStringContainsString('Buổi học còn lại', $html);
        $this->assertStringContainsString('4/10', $html);
    }

    // --- Kênh đẩy --------------------------------------------------------------------------

    public function test_score_low_and_weekly_ready_are_pushed_to_parents(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent();

        $low = new ChildScoreLow($child, 'Đề 15 phút', 35);
        $this->assertContains(WebPushChannel::class, $low->via($parent));
        $this->assertSame('Con cần hỗ trợ thêm', $low->toPush($parent)['title']);

        $weekly = new WeeklyReportReady([$child]);
        $this->assertContains(WebPushChannel::class, $weekly->via($parent));
        $this->assertArrayHasKey('url', $weekly->toPush($parent));
    }
}
