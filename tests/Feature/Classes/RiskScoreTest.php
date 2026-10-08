<?php

namespace Tests\Feature\Classes;

use App\Models\LearningPath;
use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\StudentAttendance;
use App\Models\StudentDailyActivity;
use App\Models\StudySession;
use App\Models\User;
use App\Notifications\StudyReminder;
use App\Services\Learning\RiskScoreService;
use App\Services\Parenting\ChildLinkService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/** Learning Risk Score (TA-11): công thức đặc tả, 3 mức màu, hiện cho phụ huynh và giáo viên. */
class RiskScoreTest extends ClassroomTestCase
{
    private function attendance(User $student, int $daysAgo, string $status): void
    {
        $day = today()->subDays($daysAgo);
        StudentAttendance::create([
            'student_id' => $student->id, 'attendance_date' => $day->toDateString(),
            'scheduled_start' => $day->copy()->setTime(19, 0), 'scheduled_end' => $day->copy()->setTime(20, 0),
            'scheduled_minutes' => 60, 'status' => $status, 'finalized_at' => now(),
        ]);
    }

    private function day(User $student, int $daysAgo, int $online, int $active): void
    {
        StudentDailyActivity::create([
            'user_id' => $student->id, 'activity_date' => today()->subDays($daysAgo)->toDateString(),
            'online_seconds' => $online, 'active_seconds' => $active,
        ]);
    }

    private function quizzes(User $student, array $percentsOldestFirst): void
    {
        $path = LearningPath::create(['user_id' => $student->id, 'grade_id' => $this->grade->id, 'status' => 'active', 'items_per_session' => 3, 'generated_at' => now()]);
        foreach ($percentsOldestFirst as $i => $p) {
            StudySession::create([
                'user_id' => $student->id, 'learning_path_id' => $path->id, 'session_no' => $i + 1, 'status' => 'done',
                'quiz_percent' => $p, 'quiz_submitted_at' => now()->subDays(5 - $i),
            ]);
        }
    }

    private function reminder(User $student, int $daysAgo): void
    {
        DB::table('notifications')->insert([
            'id' => (string) Str::uuid(), 'type' => StudyReminder::class, 'notifiable_type' => User::class,
            'notifiable_id' => $student->id, 'data' => '{}', 'created_at' => today()->subDays($daysAgo)->setTime(19, 0),
            'updated_at' => now(),
        ]);
    }

    /** Vắng 2/4 · dở dang 2/4 · 2/2 ngày tập trung thấp · điểm 80→60→40 · 2/2 lời nhắc bị bỏ qua. */
    private function troubledStudent(): User
    {
        $s = $this->makeStudent('Em Rủi Ro');
        $this->attendance($s, 1, 'absent');
        $this->attendance($s, 2, 'absent');
        $this->attendance($s, 3, 'partial');
        $this->attendance($s, 4, 'partial');
        $this->day($s, 3, 600, 60);
        $this->day($s, 4, 600, 60);
        $this->quizzes($s, [80, 60, 40]);
        $this->reminder($s, 1);
        $this->reminder($s, 2);

        return $s;
    }

    public function test_formula_follows_the_spec_weights(): void
    {
        $risk = app(RiskScoreService::class)->forStudent($this->troubledStudent());

        // 0.30·0.5 + 0.20·0.5 + 0.20·1 + 0.15·1 + 0.15·1 = 0.75
        $this->assertSame(75, $risk['score']);
        $this->assertSame('red', $risk['level']);
        $this->assertSame(['absenteeism' => 0.5, 'incomplete' => 0.5, 'low_engagement' => 1.0, 'quiz_decline' => 1.0, 'ignored_reminders' => 1.0], $risk['components']);
    }

    public function test_levels_and_missing_data(): void
    {
        $this->assertSame('green', RiskScoreService::levelFor(30));
        $this->assertSame('yellow', RiskScoreService::levelFor(31));
        $this->assertSame('yellow', RiskScoreService::levelFor(60));
        $this->assertSame('red', RiskScoreService::levelFor(61));

        $this->assertNull(app(RiskScoreService::class)->forStudent($this->makeStudent()));

        $steady = $this->makeStudent();
        $this->attendance($steady, 1, 'present');
        $this->day($steady, 1, 3000, 2700);
        $this->assertSame(0, app(RiskScoreService::class)->forStudent($steady)['score']);
    }

    public function test_old_data_outside_seven_days_is_ignored(): void
    {
        $s = $this->makeStudent();
        $this->attendance($s, 10, 'absent');

        $this->assertNull(app(RiskScoreService::class)->forStudent($s));
    }

    public function test_teacher_sees_risk_and_red_students_need_support(): void
    {
        $student = $this->troubledStudent();
        $this->enroll($class = $this->makeClass(), $student);

        $this->actingAs($this->teacher)
            ->get(route('teacher.students.index', ['filter' => 'needs_support']))
            ->assertOk()
            ->assertSee('Em Rủi Ro')
            ->assertSee('>75<', false);
    }

    public function test_parent_sees_traffic_light_with_explanation(): void
    {
        $student = $this->troubledStudent();
        $parent = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $parent->assignRole(Role::PARENT);
        ParentProfile::create(['user_id' => $parent->id, 'weekly_report_enabled' => true]);
        app(ChildLinkService::class)->linkByCode($parent, $student->studentProfile()->value('link_code'));

        $this->actingAs($parent)->get(route('parent.children.show', $student))
            ->assertOk()
            ->assertSee('Nguy cơ cao')
            ->assertSee('chỉ số rủi ro 75/100')
            ->assertSee('Vắng buổi theo lịch')
            // TA-16 mục 6: gợi ý can thiệp suy từ thành phần rủi ro.
            ->assertSee('Phụ huynh có thể làm gì')
            ->assertSee('đổi lịch cùng con');
    }
}
