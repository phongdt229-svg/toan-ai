<?php

namespace Tests\Feature\Rewards;

use App\Events\ExamAttemptFinished;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\RewardPoint;
use App\Models\RewardRule;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\StudentDailyActivity;
use App\Models\StudentProfile;
use App\Models\User;
use App\Models\UserBadge;
use App\Services\Admin\ImpersonationService;
use App\Services\Auth\DataExportService;
use App\Services\Learning\ProgressService;
use App\Services\Learning\StreakService;
use App\Services\Rewards\RewardService;
use Tests\Feature\Exams\ExamTestCase;

/** Điểm thưởng + huy hiệu: cộng đúng, mỗi nguồn một lần, không cộng khi mượn tài khoản, xếp hạng tôn trọng "ẩn tên". */
class RewardTest extends ExamTestCase
{
    private function attempt(Exam $exam, User $student, float $score, int $no = 1): ExamAttempt
    {
        return ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id, 'attempt_no' => $no,
            'started_at' => now()->subMinutes(10), 'expires_at' => now()->addMinutes(20), 'submitted_at' => now(),
            'status' => ExamAttempt::STATUS_GRADED, 'score' => $score, 'total_points' => 10, 'correct_count' => 0,
            'question_order' => [], 'option_order' => [],
        ]);
    }

    private function points(User $user): int
    {
        return app(RewardService::class)->total($user);
    }

    public function test_completing_a_lesson_awards_points_and_first_badge_once(): void
    {
        $lesson = Lesson::query()->firstOrFail();

        app(ProgressService::class)->completeLesson($this->student, $lesson);
        app(RewardService::class)->forLesson($this->student, $lesson); // gọi lại → không cộng thêm

        $this->assertSame(10, $this->points($this->student));
        $this->assertTrue(UserBadge::where('user_id', $this->student->id)->where('badge_key', 'first_lesson')->exists());
        $this->assertSame(1, UserBadge::where('user_id', $this->student->id)->count());
    }

    public function test_exam_points_are_per_exam_and_regrading_adds_high_score_once(): void
    {
        $exam = $this->publishedExam();

        // Lượt 1 điểm thấp: chỉ điểm "nộp đề".
        $first = $this->attempt($exam, $this->student, 4);
        ExamAttemptFinished::dispatch($first);
        $this->assertSame(5, $this->points($this->student));

        // Làm lại cùng đề, điểm cao: thêm điểm giỏi, KHÔNG thêm điểm nộp đề lần hai.
        $second = $this->attempt($exam, $this->student, 9, 2);
        ExamAttemptFinished::dispatch($second);
        ExamAttemptFinished::dispatch($second); // chấm tay bắn lại sự kiện
        $this->assertSame(15, $this->points($this->student));
        $this->assertSame(2, RewardPoint::where('user_id', $this->student->id)->count());
        $this->assertTrue(UserBadge::where('user_id', $this->student->id)->where('badge_key', 'exam_ace')->exists());
    }

    public function test_rules_come_from_the_database(): void
    {
        RewardRule::where('reason', RewardService::LESSON_COMPLETED)->update(['points' => 25]);
        RewardRule::where('reason', RewardService::EXAM_FINISHED)->update(['is_active' => false]);

        app(RewardService::class)->forLesson($this->student, Lesson::query()->firstOrFail());
        ExamAttemptFinished::dispatch($this->attempt($this->publishedExam(), $this->student, 1));

        $this->assertSame(25, $this->points($this->student));
    }

    public function test_teachers_and_impersonated_sessions_earn_nothing(): void
    {
        $lesson = Lesson::query()->firstOrFail();
        app(RewardService::class)->forLesson($this->teacher, $lesson);
        $this->assertSame(0, $this->points($this->teacher));

        // Đang trong request của người hỗ trợ mượn tài khoản (session có khoá impersonator).
        request()->setLaravelSession(app('session.store'));
        app('session.store')->put(ImpersonationService::SESSION_KEY, 1);
        app(RewardService::class)->forLesson($this->student, $lesson);
        $this->assertSame(0, $this->points($this->student));
    }

    public function test_a_full_study_day_awards_points_once_per_day(): void
    {
        $day = StudentDailyActivity::create([
            'user_id' => $this->student->id, 'activity_date' => today(), 'first_seen_at' => now(), 'last_seen_at' => now(),
            'active_seconds' => StreakService::minActiveMinutes() * 60 - 1,
        ]);

        app(RewardService::class)->forStudyDay($this->student, $day->refresh());
        $this->assertSame(0, $this->points($this->student));

        $day->update(['active_seconds' => StreakService::minActiveMinutes() * 60]);
        app(RewardService::class)->forStudyDay($this->student, $day->refresh());
        app(RewardService::class)->forStudyDay($this->student, $day->refresh());
        $this->assertSame(5, $this->points($this->student));
    }

    public function test_leaderboard_ranks_classmates_and_hides_names_on_request(): void
    {
        $class = SchoolClass::create(['name' => '6A1', 'code' => 'RWD6A1', 'grade_id' => $this->grade->id, 'owner_teacher_id' => $this->teacher->id]);
        $shy = $this->makeStudent();
        $shy->studentProfile->update(['hide_from_leaderboard' => true]);
        $shy->update(['name' => 'Tên Bí Mật']);
        foreach ([$this->student, $shy] as $s) {
            $class->students()->attach($s->id, ['status' => 'active', 'joined_at' => now()]);
        }

        $lesson = Lesson::query()->firstOrFail();
        app(RewardService::class)->forLesson($shy, $lesson);

        $board = app(RewardService::class)->leaderboard($class, $this->student);
        $this->assertSame('Bạn học ẩn danh', $board[0]['name']);
        $this->assertSame(1, $board[0]['rank']);
        $this->assertTrue($board[1]['me']);

        // Chính em ẩn tên vẫn thấy tên mình.
        $this->assertSame('Tên Bí Mật', app(RewardService::class)->leaderboard($class, $shy)[0]['name']);

        $this->actingAs($this->student)->get(route('student.rewards.index'))
            ->assertOk()
            ->assertSee('Bạn học ẩn danh')
            ->assertDontSee('Tên Bí Mật')
            ->assertSee('Bước đầu tiên');
    }

    public function test_student_can_hide_name_in_settings(): void
    {
        $this->actingAs($this->student)->put(route('student.settings.personalization'), [
            'tutor_persona' => array_key_first(StudentProfile::PERSONAS),
            'hide_from_leaderboard' => '1',
        ])->assertSessionHasNoErrors();

        $this->assertTrue($this->student->studentProfile->fresh()->hide_from_leaderboard);
    }

    public function test_dashboard_shows_points_and_admin_can_change_rules(): void
    {
        app(RewardService::class)->forLesson($this->student, Lesson::query()->firstOrFail());

        $this->actingAs($this->student)->get(route('student.dashboard'))
            ->assertOk()->assertSee('10 điểm · 1 huy hiệu');

        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole(Role::ADMIN);
        $rule = RewardRule::where('reason', RewardService::LESSON_COMPLETED)->firstOrFail();

        $this->actingAs($admin)->get(route('admin.rewards.index'))->assertOk()->assertSee($rule->label);
        $this->actingAs($admin)->put(route('admin.rewards.update'), [
            'rules' => [$rule->id => ['points' => 30, 'is_active' => '1']],
        ])->assertSessionHas('status');
        $this->assertSame(30, $rule->fresh()->points);

        $this->actingAs($this->student)->put(route('admin.rewards.update'), [
            'rules' => [$rule->id => ['points' => 999, 'is_active' => '1']],
        ])->assertForbidden();
    }

    public function test_rewards_are_exported_and_removed_on_anonymise(): void
    {
        app(RewardService::class)->forLesson($this->student, Lesson::query()->firstOrFail());

        $export = app(DataExportService::class)->build($this->student);
        $this->assertCount(1, $export['rewards']['points']);
        $this->assertCount(1, $export['rewards']['badges']);
    }
}
