<?php

namespace Tests\Feature\Parents;

use App\Models\StudentDailyActivity;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Models\User;
use App\Notifications\StudyReminder;
use Illuminate\Support\Facades\Notification;

/** Nhắc học hằng ngày + cảnh báo sắp quên bài (đặc tả module 9) — lệnh students:remind-study. */
class StudyReminderTest extends ParentTestCase
{
    private function studied(User $student, $date, int $minutes): void
    {
        StudentDailyActivity::create([
            'user_id' => $student->id, 'activity_date' => $date,
            'online_seconds' => $minutes * 60, 'active_seconds' => $minutes * 60,
        ]);
    }

    public function test_active_student_who_has_not_studied_today_is_reminded_once(): void
    {
        $student = $this->makeStudent();
        $this->studied($student, today()->subDay(), 20);

        $this->artisan('students:remind-study')->assertSuccessful();
        $this->artisan('students:remind-study')->assertSuccessful(); // chạy lại trong ngày không nhắc lần hai

        $this->assertSame(1, $student->notifications()->where('type', StudyReminder::class)->count());
    }

    public function test_student_who_already_studied_today_is_left_alone(): void
    {
        Notification::fake();
        $student = $this->makeStudent();
        $this->studied($student, today(), 12);

        $this->artisan('students:remind-study')->assertSuccessful();

        Notification::assertNotSentTo($student, StudyReminder::class);
    }

    public function test_dormant_accounts_are_not_spammed(): void
    {
        Notification::fake();
        $student = $this->makeStudent();
        $this->studied($student, today()->subDays(60), 30);

        $this->artisan('students:remind-study')->assertSuccessful();

        Notification::assertNotSentTo($student, StudyReminder::class);
    }

    public function test_reminder_names_the_topic_about_to_be_forgotten(): void
    {
        Notification::fake();
        $student = $this->makeStudent();
        $this->studied($student, today()->subDay(), 20);
        $topic = Topic::firstOrFail();
        StudentTopicMastery::create([
            'user_id' => $student->id, 'topic_id' => $topic->id, 'mastery_score' => 85,
            'correct_count' => 9, 'wrong_count' => 1, 'last_practiced_at' => now()->subDays(12),
        ]);

        $this->artisan('students:remind-study')->assertSuccessful();

        Notification::assertSentTo($student, StudyReminder::class, function (StudyReminder $n) use ($student, $topic) {
            return $n->forgettingTopic === $topic->name
                && str_contains($n->toArray($student)['message'], $topic->name);
        });
    }

    public function test_parents_are_not_targeted(): void
    {
        Notification::fake();
        $parent = $this->makeParent();

        $this->artisan('students:remind-study')->assertSuccessful();

        Notification::assertNotSentTo($parent, StudyReminder::class);
    }
}
