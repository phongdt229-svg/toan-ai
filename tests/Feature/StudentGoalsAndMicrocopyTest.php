<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\StudySchedule;
use App\Services\Learning\LearningPathService;
use App\Support\Microcopy;
use Illuminate\Support\Carbon;
use Tests\Feature\Placement\PlacementTestCase;

/** TA-05 hồ sơ năng lực · TA-06 thời lượng + mục tiêu · TA-21 microcopy theo sở thích. */
class StudentGoalsAndMicrocopyTest extends PlacementTestCase
{
    public function test_placement_result_shows_competence_per_topic(): void
    {
        $student = $this->makeStudent();
        $test = $this->completePlacement($student);

        $this->actingAs($student)->get(route('student.placement.result', $test))
            ->assertOk()
            ->assertSee('Năng lực theo chủ đề');

        $scores = $test->fresh()->load('questions.topic', 'answers')->topicScores();
        $this->assertNotEmpty($scores);
        $this->assertSame($test->total_questions, $scores->sum('total'));
    }

    public function test_big_gap_with_self_reported_average_is_explained(): void
    {
        $student = $this->makeStudent();
        $student->studentProfile()->update(['math_average_score' => 9.5]);
        $test = $this->completePlacement($student); // làm sai hết

        $gap = $test->fresh()->selfReportGap(9.5);
        $this->assertTrue($gap['suggest_retake']);

        $this->actingAs($student)->get(route('student.placement.result', $test))
            ->assertSee('làm lại bài đầu vào khi sẵn sàng');
    }

    public function test_session_size_follows_schedule_length(): void
    {
        $student = $this->makeStudent();
        $paths = app(LearningPathService::class);

        $this->assertSame(LearningPathService::ITEMS_PER_SESSION, $paths->itemsPerSessionFor($student));

        StudySchedule::create(['student_id' => $student->id, 'weekday' => 1, 'start_time' => '19:00', 'duration_minutes' => 30]);
        $this->assertSame(2, $paths->itemsPerSessionFor($student));

        $this->completePlacement($student);
        $path = $paths->active($student);
        $this->assertSame(2, $path->items_per_session);
        $this->assertTrue($path->sessions()->withCount('items')->get()->every(fn ($s) => $s->items_count <= 2));
    }

    public function test_path_page_shows_goal_and_estimated_finish(): void
    {
        $student = $this->makeStudent();
        foreach ([1, 3, 5] as $day) {
            StudySchedule::create(['student_id' => $student->id, 'weekday' => $day, 'start_time' => '19:00', 'duration_minutes' => 60]);
        }
        $student->studentProfile()->update(['target_score' => 8]);
        $this->completePlacement($student);

        $this->actingAs($student)->get(route('student.path.show'))
            ->assertOk()
            ->assertSee('em xong lộ trình khoảng')
            ->assertSee('Mục tiêu');
    }

    public function test_target_score_is_saved_from_settings(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)->put(route('student.settings.personalization'), [
            'tutor_persona' => 'co', 'target_score' => '8.5',
        ])->assertSessionHas('status');

        $this->assertSame('8.50', $student->studentProfile()->value('target_score'));

        $this->actingAs($student)->put(route('student.settings.personalization'), [
            'tutor_persona' => 'co', 'target_score' => '11',
        ])->assertSessionHasErrors('target_score');
    }

    public function test_encouragement_follows_interests_without_inventing_them(): void
    {
        $football = new StudentProfile(['interests' => ['Bóng đá', 'vẽ'], 'tutor_persona' => 'co']);
        $this->assertStringContainsString('bàn thắng', Microcopy::encouragement(new StudentProfile(['interests' => ['bong da']])));
        $this->assertStringContainsString('bàn thắng', Microcopy::encouragement($football));

        $unknown = new StudentProfile(['interests' => ['sưu tầm tem'], 'tutor_persona' => 'thay']);
        $this->assertStringStartsWith('Thầy AI', Microcopy::encouragement($unknown));

        // "vẽ" không được khớp nhầm vào chữ khác có chứa "ve" (vd "vệ sinh").
        $this->assertStringStartsWith('Cô AI', Microcopy::encouragement(new StudentProfile(['interests' => ['vệ sinh nhà cửa']])));

        $this->assertSame('Chào buổi tối', Microcopy::greeting(Carbon::parse('2026-10-09 20:00')));
    }

    public function test_dashboard_greets_by_interest(): void
    {
        $student = $this->makeStudent();
        $student->studentProfile()->update(['interests' => ['game']]);

        $this->actingAs($student)->get(route('student.dashboard'))->assertOk()->assertSee('lên cấp');
    }
}
