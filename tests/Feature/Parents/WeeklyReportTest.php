<?php

namespace Tests\Feature\Parents;

use App\Jobs\SendWeeklyParentReport;
use App\Mail\WeeklyParentReport;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\TeacherComment;
use App\Models\User;
use App\Services\Learning\StudentReportService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

class WeeklyReportTest extends ParentTestCase
{
    private function giveActivity(User $child, int $count = 3): void
    {
        $questionId = Question::value('id');

        for ($i = 0; $i < $count; $i++) {
            QuestionAttempt::create([
                'user_id' => $child->id, 'question_id' => $questionId, 'is_correct' => $i % 2 === 0,
            ]);
        }
    }

    public function test_command_queues_one_job_per_eligible_parent(): void
    {
        Queue::fake();

        $linked = $this->makeParent();
        $this->link($linked, $this->makeStudent());

        $optedOut = $this->makeParent();
        $this->link($optedOut, $this->makeStudent());
        $optedOut->parentProfile->update(['weekly_report_enabled' => false]);

        $noChild = $this->makeParent();

        $this->artisan('reports:weekly-parents')
            ->expectsOutput('Đã xếp hàng 1 báo cáo tuần.')
            ->assertSuccessful();

        Queue::assertPushed(SendWeeklyParentReport::class, 1);
        Queue::assertPushed(SendWeeklyParentReport::class, fn ($job) => $job->parentId === $linked->id);
    }

    public function test_job_sends_report_with_child_stats_and_teacher_comment(): void
    {
        Mail::fake();

        $parent = $this->makeParent();
        $child = $this->makeStudent('Phạm Bé Bi');
        $this->link($parent, $child);
        $this->giveActivity($child, 4);

        $teacher = User::factory()->create();
        TeacherComment::create([
            'teacher_id' => $teacher->id, 'student_id' => $child->id,
            'content' => 'Tuần này làm bài rất chăm.', 'visible_to_parent' => true,
        ]);

        (new SendWeeklyParentReport($parent->id))->handle(app(StudentReportService::class));

        Mail::assertSent(WeeklyParentReport::class, function (WeeklyParentReport $mail) use ($parent) {
            $week = $mail->children[0];

            return $mail->hasTo($parent->email)
                && $week['student']->name === 'Phạm Bé Bi'
                && $week['questions_answered'] === 4
                && $week['accuracy'] === 50
                && $week['comments']->count() === 1;
        });

        $this->assertNotNull($parent->parentProfile()->value('last_weekly_report_at'));
    }

    public function test_rendered_email_contains_key_lines(): void
    {
        $parent = $this->makeParent();
        $child = $this->makeStudent('Phạm Bé Bi');
        $this->link($parent, $child);
        $this->giveActivity($child, 2);

        $weekly = app(StudentReportService::class)->weekly($child, now()->subWeek(), now());
        $html = (new WeeklyParentReport($parent, [$weekly], now()->subWeek(), now()))->render();

        $this->assertStringContainsString('Phạm Bé Bi', $html);
        $this->assertStringContainsString('Câu hỏi đã làm', $html);
        $this->assertStringContainsString(route('parent.settings'), $html);
    }

    public function test_no_email_for_a_week_without_activity(): void
    {
        Mail::fake();

        $parent = $this->makeParent();
        $this->link($parent, $this->makeStudent());

        (new SendWeeklyParentReport($parent->id))->handle(app(StudentReportService::class));

        Mail::assertNothingSent();
    }

    public function test_activity_older_than_a_week_is_not_reported(): void
    {
        Mail::fake();

        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $this->link($parent, $child);

        $this->travel(-10)->days();
        $this->giveActivity($child);
        $this->travelBack();

        (new SendWeeklyParentReport($parent->id))->handle(app(StudentReportService::class));

        Mail::assertNothingSent();
    }

    public function test_job_does_not_send_twice_in_same_week(): void
    {
        Mail::fake();

        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $this->link($parent, $child);
        $this->giveActivity($child);

        $service = app(StudentReportService::class);
        (new SendWeeklyParentReport($parent->id))->handle($service);
        (new SendWeeklyParentReport($parent->id))->handle($service);

        Mail::assertSent(WeeklyParentReport::class, 1);
    }

    public function test_opted_out_parent_gets_nothing(): void
    {
        Mail::fake();

        $parent = $this->makeParent();
        $child = $this->makeStudent();
        $this->link($parent, $child);
        $this->giveActivity($child);
        $parent->parentProfile->update(['weekly_report_enabled' => false]);

        (new SendWeeklyParentReport($parent->id))->handle(app(StudentReportService::class));

        Mail::assertNothingSent();
    }
}
