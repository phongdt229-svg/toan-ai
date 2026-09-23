<?php

namespace Tests\Feature\Classes;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\User;
use App\Notifications\AssignmentDueSoon;
use Illuminate\Support\Facades\Notification;

/** Nhắc bài giao sắp tới hạn (đợt 23/09). */
class AssignmentDueReminderTest extends ClassroomTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
    }

    /** Bài giao đã công bố, hạn sau $hours giờ, có $student trong lớp. */
    private function assignmentDueIn(int $hours, User $student): Assignment
    {
        $class = $this->makeClass();
        $this->enroll($class, $student);

        return $this->questionSetAssignment($class, ['due_at' => now()->addHours($hours)]);
    }

    public function test_it_reminds_a_student_who_has_not_submitted(): void
    {
        $student = $this->makeStudent();
        $assignment = $this->assignmentDueIn(5, $student);

        $this->artisan('assignments:remind-due')->assertSuccessful();

        Notification::assertSentTo($student, AssignmentDueSoon::class);
        $this->assertNotNull(
            AssignmentStudent::where('assignment_id', $assignment->id)->where('student_id', $student->id)
                ->firstOrFail()->due_reminded_at
        );
    }

    public function test_it_stays_quiet_when_the_deadline_is_still_far_off(): void
    {
        $student = $this->makeStudent();
        $this->assignmentDueIn(72, $student);

        $this->artisan('assignments:remind-due')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_student_who_already_submitted_is_left_alone(): void
    {
        $student = $this->makeStudent();
        $assignment = $this->assignmentDueIn(3, $student);

        AssignmentStudent::where('assignment_id', $assignment->id)
            ->where('student_id', $student->id)
            ->update(['status' => AssignmentStudent::STATUS_COMPLETED]);

        $this->artisan('assignments:remind-due')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_it_never_reminds_twice_for_the_same_assignment(): void
    {
        $student = $this->makeStudent();
        $this->assignmentDueIn(6, $student);

        $this->artisan('assignments:remind-due');
        $this->artisan('assignments:remind-due');

        // Nhắc đi nhắc lại cùng một việc là cách nhanh nhất để người ta tắt thông báo.
        Notification::assertSentToTimes($student, AssignmentDueSoon::class, 1);
    }

    public function test_an_overdue_assignment_is_not_reminded(): void
    {
        $student = $this->makeStudent();
        $assignment = $this->assignmentDueIn(5, $student);
        $assignment->forceFill(['due_at' => now()->subHour()])->save();

        $this->artisan('assignments:remind-due')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_a_closed_assignment_is_not_reminded(): void
    {
        $student = $this->makeStudent();
        $assignment = $this->assignmentDueIn(5, $student);
        $assignment->forceFill(['status' => Assignment::STATUS_CLOSED])->save();

        // Đã đóng thì nộp cũng không được nữa, nhắc chỉ làm học sinh hoảng.
        $this->artisan('assignments:remind-due')->assertSuccessful();

        Notification::assertNothingSent();
    }

    public function test_it_does_not_send_email_only_the_bell_and_push(): void
    {
        $student = $this->makeStudent();
        $assignment = $this->assignmentDueIn(2, $student);

        $channels = (new AssignmentDueSoon($assignment, 2))->via($student);

        // Bài giao ngày nào cũng có — gửi email là biến hộp thư thành rác.
        $this->assertNotContains('mail', $channels);
        $this->assertContains('database', $channels);
    }
}
