<?php

namespace Tests\Feature\Placement;

use App\Models\AiUsage;
use App\Models\Grade;
use App\Models\LearningPath;
use App\Models\PlacementTest;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Services\AI\AiUsageGuard;
use App\Services\Learning\PlacementException;

class PlacementFlowTest extends PlacementTestCase
{
    // --- Ra đề ----------------------------------------------------------------------------

    public function test_student_without_grade_cannot_start(): void
    {
        $student = $this->makeStudent(['grade_id' => null]);

        $this->actingAs($student)
            ->from(route('student.placement.intro'))
            ->post(route('student.placement.start'))
            ->assertSessionHas('error', 'Em cần chọn lớp trong hồ sơ trước khi làm kiểm tra đầu vào.');
    }

    /** @return array<string, array{0: string, 1: array<string, int>}> */
    public static function levelMixes(): array
    {
        return [
            'trung bình' => ['average', ['easy' => 4, 'medium' => 3, 'hard' => 1]],
            'khá' => ['good', ['easy' => 2, 'medium' => 4, 'hard' => 2]],
        ];
    }

    /** @dataProvider levelMixes */
    public function test_question_mix_follows_self_assessed_level(string $level, array $expected): void
    {
        $student = $this->makeStudent(['self_assessed_level' => $level]);

        $test = $this->service()->start($student);

        $this->assertSame(8, $test->total_questions);
        $this->assertSame($expected, [
            'easy' => $test->questions()->where('difficulty', 'easy')->count(),
            'medium' => $test->questions()->where('difficulty', 'medium')->count(),
            'hard' => $test->questions()->where('difficulty', 'hard')->count(),
        ]);
        // Trải đều: cả hai chủ đề đều có mặt để phát hiện được điểm yếu từng chủ đề.
        $this->assertSame(2, $test->questions()->distinct('topic_id')->count('topic_id'));
        $this->assertSame(0, $test->questions()->where('type', Question::TYPE_ESSAY)->count());
    }

    public function test_shortfall_at_one_difficulty_is_filled_from_others(): void
    {
        // Giỏi cần 4 câu Khó nhưng ngân hàng chỉ có 3 → vẫn đủ 8 câu.
        $test = $this->service()->start($this->makeStudent(['self_assessed_level' => 'excellent']));

        $this->assertSame(8, $test->total_questions);
        $this->assertSame(3, $test->questions()->where('difficulty', 'hard')->count());
    }

    public function test_start_twice_resumes_same_test(): void
    {
        $student = $this->makeStudent();

        $first = $this->service()->start($student);
        $second = $this->service()->start($student);

        $this->assertSame($first->id, $second->id);
        $this->assertSame(1, PlacementTest::count());
    }

    public function test_snapshot_is_immune_to_later_bank_edits(): void
    {
        $student = $this->makeStudent();
        $test = $this->service()->start($student);
        $pq = $test->questions()->whereNotNull('question_id')->first();
        $original = $pq->content;

        Question::whereKey($pq->question_id)->update(['content' => '<p>Đã sửa trong ngân hàng</p>']);
        Question::whereKey($pq->question_id)->delete();

        $this->assertSame($original, $pq->fresh()->content);
        $this->actingAs($student)->get(route('student.placement.take', $test))->assertOk()->assertDontSee('Đã sửa trong ngân hàng');
    }

    // --- Chấm & phân tích §34 ---------------------------------------------------------------

    public function test_perfect_answers_score_ten_excellent_with_no_weak_topics(): void
    {
        $student = $this->makeStudent();
        $test = $this->service()->start($student);

        $this->actingAs($student)
            ->post(route('student.placement.submit', $test), [
                'answers' => $this->perfectAnswers($test),
                'time_spent' => $test->questions()->pluck('id')->mapWithKeys(fn ($id) => [$id => 30])->all(),
            ])
            ->assertRedirect(route('student.placement.result', $test));

        $test->refresh();
        $this->assertSame(PlacementTest::STATUS_GRADED, $test->status);
        $this->assertSame(10.0, (float) $test->score);
        $this->assertSame('excellent', $test->level_result);
        $this->assertSame(100, $test->understanding_percent);
        $this->assertSame(30, $test->avg_seconds_per_question);
        $this->assertSame('Nhanh', $test->speedLabel());
        $this->assertSame([], $test->weak_topics);
    }

    public function test_blank_answers_mark_every_topic_weak_and_level_average(): void
    {
        $test = $this->completePlacement($this->makeStudent());

        $this->assertSame(0.0, (float) $test->score);
        $this->assertSame('average', $test->level_result);
        $this->assertEqualsCanonicalizing(
            Topic::whereIn('slug', ['phep-cong-phan-so', 'so-sanh-phan-so'])->pluck('id')->all(),
            collect($test->weak_topics)->pluck('topic_id')->all(),
        );
    }

    public function test_bank_questions_feed_mastery_history(): void
    {
        $student = $this->makeStudent();
        $test = $this->completePlacement($student);

        $this->assertSame($test->total_questions, QuestionAttempt::where('context', 'placement')->where('context_id', $test->id)->count());
        $this->assertTrue(StudentTopicMastery::where('user_id', $student->id)->exists());
    }

    public function test_submission_after_grace_period_ignores_late_answers(): void
    {
        $student = $this->makeStudent();
        $test = $this->service()->start($student);
        $answers = $this->perfectAnswers($test);

        $this->travel(21)->minutes();

        $this->actingAs($student)->post(route('student.placement.submit', $test), ['answers' => $answers]);

        $test->refresh();
        $this->assertTrue($test->auto_submitted);
        $this->assertSame(0.0, (float) $test->score);
        $this->assertTrue($test->submitted_at->equalTo($test->expires_at));
    }

    public function test_abandoned_test_is_finalized_by_scheduled_command(): void
    {
        $this->service()->start($this->makeStudent());
        $this->travel(25)->minutes();

        $this->artisan('exams:finalize-expired')->expectsOutput('Đã tự nộp 1 lượt làm bài.');

        $this->assertSame(PlacementTest::STATUS_GRADED, PlacementTest::first()->status);
    }

    public function test_result_generates_learning_path_and_ai_analysis(): void
    {
        $student = $this->makeStudent();
        $test = $this->completePlacement($student);

        $this->assertNotEmpty($test->analysis);
        $this->assertSame($test->id, LearningPath::where('user_id', $student->id)->value('placement_test_id'));
        $this->assertStringContainsString('Nhiệm vụ lượt này: Viết nhận xét', $this->fake()->lastRequest()->messages[0]['content']);

        $this->actingAs($student)
            ->get(route('student.placement.result', $test))
            ->assertOk()
            ->assertSee('Mức độ hiểu')
            ->assertSee('Kiến thức cần củng cố')
            ->assertSee('Xem lộ trình học của em');
    }

    public function test_ai_failure_does_not_block_result_or_path(): void
    {
        $student = $this->makeStudent();
        $test = $this->service()->start($student);
        $this->fake()->failNext();

        $test = $this->service()->submit($test, []);

        $this->assertNull($test->analysis);
        $this->assertSame(PlacementTest::STATUS_GRADED, $test->status);
        $this->assertTrue(LearningPath::where('user_id', $student->id)->exists());
    }

    // --- Ngân hàng thiếu câu → AI bù ------------------------------------------------------------

    private function sparseGrade(): Grade
    {
        // Lớp 7 chỉ có 2 câu → dưới ngưỡng tối thiểu 5 câu.
        $grade = Grade::where('level', 7)->firstOrFail();
        $subject = $grade->subjects()->create(['name' => 'Toán', 'slug' => 'toan']);
        $chapter = $subject->chapters()->create(['name' => 'Số hữu tỉ', 'slug' => 'so-huu-ti']);
        $topic = $chapter->topics()->create(['name' => 'Cộng số hữu tỉ', 'slug' => 'cong-so-huu-ti']);

        foreach (['easy', 'medium'] as $d) {
            Question::create([
                'grade_id' => $grade->id, 'topic_id' => $topic->id, 'type' => 'short_answer', 'difficulty' => $d,
                'content' => "<p>Câu {$d}</p>", 'correct_answer' => ['accepted' => ['1']], 'status' => 'published',
            ]);
        }

        return $grade;
    }

    public function test_ai_fills_missing_questions_without_using_student_quota(): void
    {
        $grade = $this->sparseGrade();
        $student = $this->makeStudent(['grade_id' => $grade->id]);

        $test = $this->service()->start($student);

        $this->assertSame(8, $test->total_questions);
        $this->assertSame(2, $test->questions()->whereNotNull('question_id')->count());
        $this->assertSame(6, $test->questions()->whereNull('question_id')->count());

        // Câu AI sinh vẫn chấm được bằng GradingService qua bản chụp.
        $test = $this->service()->submit($test, $this->perfectAnswers($test));
        $this->assertSame(10.0, (float) $test->score);

        $this->assertTrue(AiUsage::where('user_id', $student->id)->where('feature', 'placement_generate')->exists());
        $this->assertSame(0, app(AiUsageGuard::class)->usedToday($student), 'Lượt AI của hệ thống không trừ quota học sinh.');
    }

    public function test_friendly_error_when_bank_is_short_and_ai_fails(): void
    {
        $grade = $this->sparseGrade();
        $student = $this->makeStudent(['grade_id' => $grade->id]);
        $this->fake()->failNext();

        $this->expectException(PlacementException::class);
        $this->expectExceptionMessage('chưa đủ câu hỏi');

        $this->service()->start($student);
    }

    // --- Quyền ---------------------------------------------------------------------------------

    public function test_other_student_cannot_see_or_submit_someone_elses_test(): void
    {
        $test = $this->service()->start($this->makeStudent());
        $intruder = $this->makeStudent();

        $this->actingAs($intruder)->get(route('student.placement.take', $test))->assertForbidden();
        $this->actingAs($intruder)->post(route('student.placement.submit', $test), ['answers' => []])->assertForbidden();
        $this->actingAs($intruder)->get(route('student.placement.result', $test))->assertForbidden();
    }

    public function test_dashboard_invites_student_without_placement(): void
    {
        $this->actingAs($this->makeStudent())
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Làm kiểm tra đầu vào để nhận lộ trình riêng');
    }
}
