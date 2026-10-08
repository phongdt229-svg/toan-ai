<?php

namespace Tests\Feature\Parents;

use App\Models\LearningPath;
use App\Models\StudentAttendance;
use App\Models\StudentDailyActivity;
use App\Models\StudySchedule;
use App\Models\StudySession;
use App\Models\User;
use App\Notifications\ChildAttendanceAlert;
use App\Services\Learning\AttendanceService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

/** Điểm danh theo lịch học (TA-09) + xử lý vắng (TA-10). Lịch mẫu: hôm nay 19:00, 60 phút. */
class AttendanceTest extends ParentTestCase
{
    private User $parent;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->parent = $this->makeParent();
        $this->student = $this->makeStudent();
        $this->link($this->parent, $this->student);

        $this->travelTo(today()->setTime(18, 0));
        StudySchedule::create([
            'student_id' => $this->student->id, 'weekday' => today()->isoWeekday(),
            'start_time' => '19:00', 'duration_minutes' => 60,
        ]);
    }

    private function at(int $h, int $m): void
    {
        $this->travelTo(today()->setTime($h, $m));
        app(AttendanceService::class)->tick();
    }

    private function studiedTotal(int $minutes): void
    {
        StudentDailyActivity::updateOrCreate(
            ['user_id' => $this->student->id, 'activity_date' => today()->toDateString()],
            ['online_seconds' => $minutes * 60, 'active_seconds' => $minutes * 60, 'last_active_at' => now()],
        );
    }

    private function row(): StudentAttendance
    {
        return StudentAttendance::where('student_id', $this->student->id)->latest('attendance_date')->firstOrFail();
    }

    public function test_nothing_happens_before_the_slot_starts(): void
    {
        $this->at(18, 59);

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_no_show_goes_late_then_parent_is_told_at_fifteen_minutes(): void
    {
        $this->at(19, 0);
        $this->assertSame(StudentAttendance::STATUS_PENDING, $this->row()->status);

        $this->at(19, 11);
        $this->assertSame(StudentAttendance::STATUS_LATE, $this->row()->status);
        Notification::assertNothingSentTo($this->parent);

        $this->at(19, 16);
        $this->at(19, 20); // không báo lần hai
        $this->assertSame(StudentAttendance::STATUS_ABSENT_PENDING, $this->row()->status);
        Notification::assertSentToTimes($this->parent, ChildAttendanceAlert::class, 1);

        // TA-16 mục 1–2: trang giám sát cho thấy ngay hôm nay con chưa vào học.
        $this->actingAs($this->parent)->get(route('parent.children.show', $this->student))
            ->assertOk()->assertSee('Hôm nay: <strong>19:00</strong>', false)->assertSee('Chưa vào học');
        Notification::assertSentTo($this->parent, ChildAttendanceAlert::class,
            fn (ChildAttendanceAlert $n) => $n->kind === ChildAttendanceAlert::NOT_STARTED);
    }

    public function test_no_activity_for_the_whole_slot_is_absent_and_parent_gets_next_slot(): void
    {
        StudySchedule::create([
            'student_id' => $this->student->id, 'weekday' => today()->addDay()->isoWeekday(),
            'start_time' => '18:30', 'duration_minutes' => 45,
        ]);

        $this->at(19, 0);
        $this->at(20, 0);

        $this->assertSame(StudentAttendance::STATUS_ABSENT, $this->row()->status);
        Notification::assertSentTo($this->parent, ChildAttendanceAlert::class, function (ChildAttendanceAlert $n) {
            return $n->kind === ChildAttendanceAlert::ABSENT
                && $n->absentStreak === 1
                && $n->nextSlot?->format('H:i') === '18:30'
                && ! in_array('mail', $n->via($this->parent), true);
        });
    }

    public function test_studying_most_of_the_slot_without_a_path_is_present(): void
    {
        $this->at(19, 0);
        $this->travelTo(today()->setTime(19, 50));
        $this->studiedTotal(45); // ≥ 70% của 60 phút
        $this->at(20, 0);

        $row = $this->row();
        $this->assertSame(StudentAttendance::STATUS_PRESENT, $row->status);
        $this->assertSame(45, $row->activeMinutes());
        Notification::assertNothingSentTo($this->parent);
    }

    public function test_coming_late_and_studying_briefly_is_partial(): void
    {
        $this->at(19, 0);
        $this->travelTo(today()->setTime(19, 20));
        $this->studiedTotal(10);
        $this->at(19, 20);

        $this->assertSame(StudentAttendance::STATUS_IN_PROGRESS, $this->row()->status);
        $this->assertSame(20, $this->row()->late_minutes);

        $this->at(20, 0);
        $this->assertSame(StudentAttendance::STATUS_PARTIAL, $this->row()->status);
    }

    public function test_study_before_the_slot_does_not_count_towards_it(): void
    {
        $this->studiedTotal(50); // học buổi chiều, trước giờ lịch
        $this->at(19, 0);
        $this->at(20, 0);

        $this->assertSame(StudentAttendance::STATUS_ABSENT, $this->row()->status);
        $this->assertSame(0, $this->row()->active_seconds);
    }

    public function test_two_absences_in_a_row_escalate_to_email_and_show_on_parent_page(): void
    {
        // Buổi hôm qua đã vắng.
        StudentAttendance::create([
            'student_id' => $this->student->id, 'attendance_date' => today()->subDay()->toDateString(),
            'scheduled_start' => today()->subDay()->setTime(19, 0), 'scheduled_end' => today()->subDay()->setTime(20, 0),
            'scheduled_minutes' => 60, 'status' => StudentAttendance::STATUS_ABSENT, 'finalized_at' => now(),
        ]);

        $this->at(19, 0);
        $this->at(20, 0);

        Notification::assertSentTo($this->parent, ChildAttendanceAlert::class, function (ChildAttendanceAlert $n) {
            return $n->kind === ChildAttendanceAlert::ABSENT && $n->absentStreak === 2 && in_array('mail', $n->via($this->parent), true);
        });

        $this->actingAs($this->parent)->get(route('parent.children.show', $this->student))
            ->assertOk()
            ->assertSee('vắng <strong>2 buổi liên tiếp</strong>', false)
            ->assertSee('Vắng');
    }

    // --- TA-12: các luật cảnh báo còn lại -----------------------------------------------------

    private function activePath(): LearningPath
    {
        return LearningPath::create([
            'user_id' => $this->student->id, 'grade_id' => $this->grade->id, 'status' => 'active', 'items_per_session' => 3, 'generated_at' => now(),
        ]);
    }

    public function test_entered_but_idle_ten_minutes_is_flagged_not_notified(): void
    {
        $this->at(19, 0);
        $this->travelTo(today()->setTime(19, 5));
        $this->studiedTotal(5);
        $this->at(19, 5);
        $this->assertNull($this->row()->idle_flagged_at);

        $this->at(19, 16); // không thao tác từ 19:05
        $this->assertNotNull($this->row()->idle_flagged_at);
        Notification::assertNothingSentTo($this->parent);
    }

    public function test_enough_time_but_skipped_quiz_warns_parent(): void
    {
        $this->activePath();
        $this->at(19, 0);
        $this->travelTo(today()->setTime(19, 55));
        $this->studiedTotal(50);
        $this->at(20, 0);

        $this->assertSame(StudentAttendance::STATUS_PARTIAL, $this->row()->status);
        Notification::assertSentTo($this->parent, ChildAttendanceAlert::class,
            fn (ChildAttendanceAlert $n) => $n->kind === ChildAttendanceAlert::QUIZ_MISSED);
    }

    public function test_three_falling_quiz_scores_suggest_parent_follow_up(): void
    {
        $path = $this->activePath();
        foreach ([[1, 90, 3], [2, 70, 2]] as [$no, $percent, $daysAgo]) {
            StudySession::create([
                'user_id' => $this->student->id, 'learning_path_id' => $path->id, 'session_no' => $no, 'status' => 'done',
                'quiz_percent' => $percent, 'quiz_submitted_at' => today()->subDays($daysAgo)->setTime(19, 40),
            ]);
        }

        $this->at(19, 0);
        $this->travelTo(today()->setTime(19, 50));
        $this->studiedTotal(48);
        StudySession::create([
            'user_id' => $this->student->id, 'learning_path_id' => $path->id, 'session_no' => 3, 'status' => 'done',
            'quiz_percent' => 40, 'quiz_submitted_at' => now(),
        ]);
        $this->at(20, 0);

        $this->assertSame(StudentAttendance::STATUS_PRESENT, $this->row()->status);
        Notification::assertSentTo($this->parent, ChildAttendanceAlert::class,
            fn (ChildAttendanceAlert $n) => $n->kind === ChildAttendanceAlert::QUIZ_DECLINE);
    }

    public function test_day_without_schedule_creates_nothing(): void
    {
        StudySchedule::query()->delete();

        $this->at(19, 30);

        $this->assertDatabaseCount('student_attendances', 0);
    }

    public function test_command_runs(): void
    {
        $this->travelTo(today()->setTime(19, 5));
        $this->artisan('attendance:tick')->assertSuccessful();

        $this->assertSame(1, StudentAttendance::count());
        $this->assertInstanceOf(Carbon::class, $this->row()->scheduled_start);
    }
}
