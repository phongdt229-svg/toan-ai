<?php

namespace Tests\Feature\Exams;

use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Role;
use App\Models\StudentAnswer;
use App\Models\User;

class ExamManualGradingTest extends ExamTestCase
{
    /** Học sinh làm đúng hết phần tự chấm, câu tự luận có viết bài → chờ chấm. */
    private function submittedAttemptWithEssay(): ExamAttempt
    {
        $exam = $this->publishedExam();
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();

        foreach ($exam->questions as $q) {
            $this->actingAs($this->student)->postJson(route('student.exams.answer', $attempt), [
                'question_id' => $q->id,
                'value' => $this->correctValue($q),
            ]);
        }

        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        return $attempt->fresh();
    }

    private function essayAnswer(ExamAttempt $attempt): StudentAnswer
    {
        $essayId = Question::where('type', Question::TYPE_ESSAY)->value('id');

        return StudentAnswer::where('exam_attempt_id', $attempt->id)->where('question_id', $essayId)->firstOrFail();
    }

    public function test_teacher_grades_essay_and_attempt_becomes_graded(): void
    {
        $attempt = $this->submittedAttemptWithEssay();
        $this->assertSame(ExamAttempt::STATUS_SUBMITTED, $attempt->status);

        $answer = $this->essayAnswer($attempt);
        $this->assertNull($answer->score);

        $this->actingAs($this->teacher)
            ->get(route('teacher.exams.grade', $attempt))
            ->assertOk()
            ->assertSee('Lưu điểm');

        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.grade.answer', $answer), ['score' => 2.5, 'feedback' => 'Thiếu bước rút gọn.'])
            ->assertSessionHas('status');

        $attempt->refresh();
        $this->assertSame(ExamAttempt::STATUS_GRADED, $attempt->status);
        $this->assertSame(11.5, (float) $attempt->score);   // 9 tự chấm + 2.5 tự luận
        $this->assertSame(7, $attempt->correct_count);       // 2.5/3 ≥ 50% → tính là đúng

        $answer->refresh();
        $this->assertSame($this->teacher->id, $answer->graded_by);
        $this->assertSame('Thiếu bước rút gọn.', $answer->feedback);

        // Lịch sử cho AI cũng được cập nhật theo điểm chấm tay.
        $history = QuestionAttempt::where('context_id', $attempt->id)->where('question_id', $answer->question_id)->firstOrFail();
        $this->assertTrue($history->is_correct);
        $this->assertSame(2.5, (float) $history->score);

        $this->actingAs($this->student)
            ->get(route('student.exams.result', $attempt))
            ->assertSee('Thiếu bước rút gọn.');
    }

    public function test_score_above_max_is_rejected(): void
    {
        $attempt = $this->submittedAttemptWithEssay();
        $answer = $this->essayAnswer($attempt);

        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.grade.answer', $answer), ['score' => 99])
            ->assertSessionHasErrors("score.{$answer->id}");

        $this->assertNull($answer->fresh()->score);
        $this->assertSame(ExamAttempt::STATUS_SUBMITTED, $attempt->fresh()->status);
    }

    public function test_low_essay_score_counts_as_wrong(): void
    {
        $attempt = $this->submittedAttemptWithEssay();
        $answer = $this->essayAnswer($attempt);

        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.grade.answer', $answer), ['score' => 1]);

        $this->assertFalse($answer->fresh()->is_correct);
        $this->assertSame(6, $attempt->fresh()->correct_count);
    }

    public function test_regrading_replaces_previous_score(): void
    {
        $attempt = $this->submittedAttemptWithEssay();
        $answer = $this->essayAnswer($attempt);

        $this->actingAs($this->teacher)->post(route('teacher.exams.grade.answer', $answer), ['score' => 3]);
        $this->actingAs($this->teacher)->post(route('teacher.exams.grade.answer', $answer), ['score' => 1]);

        $this->assertSame(10.0, (float) $attempt->fresh()->score);
    }

    public function test_other_teacher_cannot_grade(): void
    {
        $attempt = $this->submittedAttemptWithEssay();
        $answer = $this->essayAnswer($attempt);

        $other = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $other->assignRole(Role::TEACHER);

        $this->actingAs($other)->get(route('teacher.exams.grade', $attempt))->assertForbidden();
        $this->actingAs($other)
            ->post(route('teacher.exams.grade.answer', $answer), ['score' => 3])
            ->assertForbidden();

        $this->assertNull($answer->fresh()->score);
    }

    public function test_attempts_list_filters_pending(): void
    {
        $attempt = $this->submittedAttemptWithEssay();

        $this->actingAs($this->teacher)
            ->get(route('teacher.exams.attempts', [$attempt->exam, 'pending' => 1]))
            ->assertOk()
            ->assertSee($this->student->name)
            ->assertSee('Chấm bài');
    }
}
