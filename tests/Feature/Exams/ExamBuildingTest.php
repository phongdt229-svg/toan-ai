<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Role;
use App\Models\User;
use App\Services\Teaching\ExamBuilderService;

class ExamBuildingTest extends ExamTestCase
{
    private function payload(array $extra = []): array
    {
        return [
            'title' => 'Kiểm tra chương Phân số',
            'grade_id' => $this->grade->id,
            'type' => 'test',
            'duration_minutes' => 45,
            'difficulty' => 'mixed',
            'access_level' => 'free',
            'max_attempts' => 1,
            'shuffle_questions' => '1',
            'show_answers_after_submit' => '1',
            ...$extra,
        ];
    }

    public function test_teacher_creates_draft_exam(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.store'), $this->payload())
            ->assertRedirect();

        $exam = Exam::firstOrFail();

        $this->assertSame(Exam::STATUS_DRAFT, $exam->status);
        $this->assertSame('kiem-tra-chuong-phan-so', $exam->slug);
        $this->assertTrue($exam->shuffle_questions);
        // Checkbox không tick → false, không phải giữ default true.
        $this->assertFalse($exam->shuffle_options);
    }

    public function test_add_questions_from_bank_updates_totals(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.exams.store'), $this->payload());
        $exam = Exam::firstOrFail();

        $ids = Question::where('type', '!=', Question::TYPE_ESSAY)->limit(3)->pluck('id')->all();

        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.add', $exam), ['question_ids' => $ids])
            ->assertSessionHas('status', 'Đã thêm 3 câu hỏi vào đề.');

        // Thêm lại trùng thì bỏ qua.
        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.add', $exam), ['question_ids' => $ids])
            ->assertSessionHas('status', 'Đã thêm 0 câu hỏi vào đề.');

        $exam->refresh();
        $this->assertSame(3, $exam->total_questions);
        $this->assertSame(
            (float) Question::whereIn('id', $ids)->sum('points'),
            (float) $exam->total_points,
        );
    }

    public function test_quota_split_follows_ratio_and_assigns_remainder(): void
    {
        $builder = app(ExamBuilderService::class);

        $this->assertSame(['easy' => 3, 'medium' => 5, 'hard' => 2], $builder->quotas(10, ['easy' => 30, 'medium' => 50, 'hard' => 20]));
        // 7 câu × (30/50/20) = 2.1 / 3.5 / 1.4 → 2/3/1, dư 1 dồn cho nhóm tỉ lệ lớn nhất.
        $this->assertSame(['easy' => 2, 'medium' => 4, 'hard' => 1], $builder->quotas(7, ['easy' => 30, 'medium' => 50, 'hard' => 20]));
    }

    public function test_random_pick_respects_bank_size_and_reports_shortfall(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.exams.store'), $this->payload());
        $exam = Exam::firstOrFail();

        $bankSize = Question::published()->where('grade_id', $this->grade->id)->count();

        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.random', $exam), [
                'count' => 50, 'easy' => 30, 'medium' => 50, 'hard' => 20,
            ])
            ->assertSessionHas('status', "Ngân hàng chỉ đủ {$bankSize}/50 câu phù hợp — đã thêm {$bankSize} câu.");

        $this->assertSame($bankSize, $exam->fresh()->total_questions);
    }

    public function test_random_ratio_must_sum_to_100(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.exams.store'), $this->payload());
        $exam = Exam::firstOrFail();

        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.questions.random', $exam), [
                'count' => 5, 'easy' => 50, 'medium' => 50, 'hard' => 50,
            ])
            ->assertSessionHas('error');

        $this->assertSame(0, $exam->fresh()->total_questions);
    }

    public function test_empty_exam_cannot_be_published(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.exams.store'), $this->payload());
        $exam = Exam::firstOrFail();

        $this->actingAs($this->teacher)
            ->post(route('teacher.exams.publish', $exam))
            ->assertSessionHas('error', 'Đề chưa có câu hỏi nào, không thể xuất bản.');

        $this->assertSame(Exam::STATUS_DRAFT, $exam->fresh()->status);
    }

    public function test_question_set_is_locked_once_students_have_attempted(): void
    {
        $exam = $this->publishedExam();
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $this->assertSame(1, ExamAttempt::count());

        $question = $exam->questions->first();

        $this->actingAs($this->teacher)
            ->delete(route('teacher.exams.questions.remove', [$exam, $question]))
            ->assertSessionHas('error', 'Đề đã có học sinh làm, không thể thay đổi câu hỏi hoặc điểm.');

        $this->actingAs($this->teacher)
            ->put(route('teacher.exams.questions.update', [$exam, $question]), ['points' => 10, 'sort_order' => 1])
            ->assertSessionHas('error');

        $this->assertSame(7, $exam->fresh()->total_questions);
    }

    public function test_grade_cannot_change_after_questions_added(): void
    {
        $exam = $this->publishedExam();
        $otherGrade = Grade::where('level', 7)->firstOrFail();

        $this->actingAs($this->teacher)
            ->put(route('teacher.exams.update', $exam), $this->payload(['grade_id' => $otherGrade->id]))
            ->assertSessionHasErrors('grade_id');
    }

    public function test_teacher_cannot_touch_another_teachers_exam(): void
    {
        $exam = $this->publishedExam();

        $other = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $other->assignRole(Role::TEACHER);

        $this->actingAs($other)->get(route('teacher.exams.edit', $exam))->assertForbidden();
        $this->actingAs($other)->post(route('teacher.exams.publish', $exam))->assertForbidden();
        $this->actingAs($other)->get(route('teacher.exams.attempts', $exam))->assertForbidden();
        $this->actingAs($other)->delete(route('teacher.exams.destroy', $exam))->assertForbidden();
    }

    public function test_deleting_exam_keeps_student_attempts(): void
    {
        $exam = $this->publishedExam();
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        $this->actingAs($this->teacher)->delete(route('teacher.exams.destroy', $exam));

        $this->assertSoftDeleted($exam);
        // Học sinh vẫn xem lại được điểm của mình.
        $this->actingAs($this->student)->get(route('student.exams.result', $attempt))->assertOk();
    }
}
