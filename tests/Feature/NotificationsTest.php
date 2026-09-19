<?php

namespace Tests\Feature;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\ExamAttempt;
use App\Models\Package;
use App\Models\Payment;
use App\Models\Question;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\Subscription;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AssignmentSubmittedByStudent;
use App\Notifications\ChildScoreLow;
use App\Notifications\ExamResultReady;
use App\Notifications\PaymentSucceeded as PaymentSucceededNotification;
use App\Notifications\SupportTicketResolved;
use App\Notifications\TeacherAccountApproved;
use App\Services\SupportTicketService;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Exams\ExamTestCase;

class NotificationsTest extends ExamTestCase
{
    // --- Học sinh làm đề xong -----------------------------------------------------------

    public function test_grading_an_exam_notifies_the_student(): void
    {
        Notification::fake();

        $exam = $this->publishedExam();
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        // Nộp trắng: không có tự luận nào chờ chấm tay → graded ngay (xem ExamTakingTest).
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        Notification::assertSentTo($this->student, ExamResultReady::class);
    }

    public function test_essay_pending_grading_does_not_notify_until_graded(): void
    {
        Notification::fake();

        $exam = $this->publishedExam();
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();

        $essay = $exam->questions->firstWhere('type', Question::TYPE_ESSAY);
        $this->actingAs($this->student)->postJson(route('student.exams.answer', $attempt), [
            'question_id' => $essay->id, 'value' => 'Bài làm của em', 'time_spent' => 30,
        ]);
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        // Còn tự luận chờ chấm — chưa có điểm cuối để báo.
        Notification::assertNotSentTo($this->student, ExamResultReady::class);
    }

    // --- Phụ huynh: điểm thấp -------------------------------------------------------------

    public function test_low_score_notifies_linked_parents(): void
    {
        $parent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $parent->assignRole(Role::PARENT);
        $parent->children()->attach($this->student->id, ['status' => 'linked', 'linked_at' => now()]);

        Notification::fake();

        $exam = $this->publishedExam();
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt)); // trắng -> 0 điểm

        Notification::assertSentTo($parent, ChildScoreLow::class);
    }

    public function test_perfect_score_does_not_notify_parents(): void
    {
        $parent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $parent->assignRole(Role::PARENT);
        $parent->children()->attach($this->student->id, ['status' => 'linked', 'linked_at' => now()]);

        Notification::fake();

        $exam = $this->publishedExam();
        $essay = $exam->questions->firstWhere('type', Question::TYPE_ESSAY);
        $exam->questions()->detach($essay->id); // chỉ còn câu tự chấm được

        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();

        foreach ($exam->questions()->get() as $q) {
            $this->actingAs($this->student)->postJson(route('student.exams.answer', $attempt), [
                'question_id' => $q->id, 'value' => $this->correctValue($q), 'time_spent' => 10,
            ]);
        }
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        Notification::assertNotSentTo($parent, ChildScoreLow::class);
    }

    // --- Giáo viên: học sinh nộp bài giao --------------------------------------------------

    public function test_completing_an_exam_assignment_notifies_the_teacher(): void
    {
        $class = SchoolClass::create([
            'name' => 'Lớp thử', 'code' => strtoupper(substr(uniqid(), -6)),
            'grade_id' => $this->grade->id, 'owner_teacher_id' => $this->teacher->id,
            'status' => SchoolClass::STATUS_ACTIVE,
        ]);

        $exam = $this->publishedExam();
        $assignment = Assignment::create([
            'class_id' => $class->id, 'teacher_id' => $this->teacher->id, 'title' => 'Đề giao về nhà',
            'type' => Assignment::TYPE_EXAM, 'exam_id' => $exam->id, 'assign_to_all' => true,
            'status' => Assignment::STATUS_PUBLISHED, 'published_at' => now()->subHour(),
        ]);
        AssignmentStudent::create([
            'assignment_id' => $assignment->id, 'student_id' => $this->student->id,
            'status' => AssignmentStudent::STATUS_ASSIGNED,
        ]);

        Notification::fake();

        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        Notification::assertSentTo(
            $this->teacher,
            fn (AssignmentSubmittedByStudent $n) => $n->assignment->is($assignment) && $n->student->is($this->student),
        );
    }

    // --- Giáo viên được duyệt --------------------------------------------------------------

    public function test_approving_a_teacher_notifies_them(): void
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole(Role::ADMIN);

        $pending = User::factory()->create(['status' => User::STATUS_PENDING]);
        $pending->assignRole(Role::TEACHER);
        $pending->teacherProfile()->create(['school' => 'THCS Demo', 'subject' => 'Toán']);

        Notification::fake();

        $this->actingAs($admin)->post(route('admin.teachers.approve', $pending))->assertRedirect();

        Notification::assertSentTo($pending, TeacherAccountApproved::class);
    }

    // --- Yêu cầu hỗ trợ đã xử lý -------------------------------------------------------------

    public function test_resolving_a_ticket_notifies_its_owner(): void
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $ticket = SupportTicket::create([
            'code' => SupportTicket::generateCode(), 'type' => SupportTicket::TYPE_SUPPORT,
            'user_id' => $this->student->id, 'name' => $this->student->name, 'email' => $this->student->email,
            'subject' => 'Không đăng nhập được', 'message' => 'Giúp em với.', 'status' => SupportTicket::STATUS_NEW,
        ]);

        Notification::fake();

        app(SupportTicketService::class)->update($ticket, $admin, SupportTicket::STATUS_RESOLVED, 'Đã xử lý.');

        Notification::assertSentTo($this->student, SupportTicketResolved::class);
    }

    public function test_resolving_a_guest_ticket_does_not_crash_or_notify_anyone(): void
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $ticket = SupportTicket::create([
            'code' => SupportTicket::generateCode(), 'type' => SupportTicket::TYPE_SUPPORT,
            'user_id' => null, 'name' => 'Khách', 'email' => 'khach@example.com',
            'subject' => 'Hỏi về gói học', 'message' => 'Cho hỏi...', 'status' => SupportTicket::STATUS_NEW,
        ]);

        Notification::fake();

        app(SupportTicketService::class)->update($ticket, $admin, SupportTicket::STATUS_RESOLVED, null);

        Notification::assertNothingSent();
    }

    // --- Thanh toán thành công (kênh database) ----------------------------------------------

    public function test_payment_succeeded_creates_a_database_notification(): void
    {
        $package = Package::create([
            'name' => 'Pro 1 tháng', 'slug' => 'pro-thang-test', 'tier' => Package::TIER_PRO,
            'price' => 99000, 'currency' => 'VND', 'duration_days' => 30, 'is_active' => true,
        ]);
        $subscription = Subscription::create([
            'user_id' => $this->student->id, 'purchased_by' => $this->student->id, 'package_id' => $package->id,
            'status' => 'active', 'price_paid' => $package->price, 'duration_days' => 30,
            'starts_at' => now(), 'ends_at' => now()->addDays(30), 'activated_at' => now(), 'source' => 'payment',
        ]);
        $payment = Payment::create([
            'order_code' => 'TEST'.uniqid(), 'user_id' => $this->student->id, 'package_id' => $package->id,
            'subscription_id' => $subscription->id,
            'amount' => $package->price, 'status' => Payment::STATUS_PAID, 'paid_at' => now(),
        ]);

        $this->student->notify(new PaymentSucceededNotification($payment));

        $this->assertDatabaseHas('notifications', ['notifiable_id' => $this->student->id, 'type' => PaymentSucceededNotification::class]);
        $data = $this->student->notifications()->first()->data;
        $this->assertSame(route('payment.show', $payment), $data['url']);
    }

    // --- Chuông + trang "xem tất cả" ---------------------------------------------------------

    public function test_bell_shows_unread_badge_and_list(): void
    {
        $this->student->notify(new TeacherAccountApproved); // nội dung không quan trọng, chỉ cần có 1 thông báo

        $this->actingAs($this->student)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('bi-bell', false)
            ->assertSee('badge rounded-pill bg-danger', false);
    }

    public function test_opening_a_notification_marks_it_read_and_redirects(): void
    {
        $this->student->notify(new TeacherAccountApproved);
        $notification = $this->student->notifications()->firstOrFail();

        $this->assertNull($notification->read_at);

        $this->actingAs($this->student)
            ->get(route('notifications.open', $notification))
            ->assertRedirect($notification->data['url']);

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cannot_open_someone_elses_notification(): void
    {
        $this->student->notify(new TeacherAccountApproved);
        $notification = $this->student->notifications()->firstOrFail();

        $other = $this->makeStudent();

        $this->actingAs($other)->get(route('notifications.open', $notification))->assertNotFound();
    }

    public function test_mark_all_read_clears_unread_count(): void
    {
        $this->student->notify(new TeacherAccountApproved);
        $this->student->notify(new TeacherAccountApproved);

        $this->assertSame(2, $this->student->unreadNotifications()->count());

        $this->actingAs($this->student)->post(route('notifications.read_all'))->assertRedirect();

        $this->assertSame(0, $this->student->unreadNotifications()->count());
    }
}
