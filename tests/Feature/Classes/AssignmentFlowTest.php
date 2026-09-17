<?php

namespace Tests\Feature\Classes;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\StudentAnswer;
use App\Services\Learning\ProgressService;

class AssignmentFlowTest extends ClassroomTestCase
{
    // --- Giao bài -------------------------------------------------------------

    public function test_teacher_assigns_question_set_to_whole_class(): void
    {
        $class = $this->makeClass();
        [$a, $b] = [$this->makeStudent(), $this->makeStudent()];
        $this->enroll($class, $a, $b);

        $this->actingAs($this->teacher)
            ->post(route('teacher.assignments.store'), [
                'class_id' => $class->id,
                'title' => 'BTVN tuần 1',
                'type' => 'question_set',
                'question_ids' => $this->autoGradableQuestionIds(),
                'assign_to_all' => '1',
                'due_at' => now()->addDays(2)->format('Y-m-d\TH:i'),
            ])
            ->assertRedirect();

        $assignment = Assignment::firstOrFail();

        $this->assertSame(2, $assignment->recipients()->count());
        $this->assertSame(count($this->autoGradableQuestionIds()), $assignment->questions()->count());
        $this->assertSame(1, $assignment->effectiveMaxAttempts());
    }

    public function test_assign_to_selected_students_only(): void
    {
        $class = $this->makeClass();
        [$a, $b] = [$this->makeStudent(), $this->makeStudent()];
        $this->enroll($class, $a, $b);
        $outsider = $this->makeStudent();

        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), [
            'class_id' => $class->id,
            'title' => 'Bài riêng',
            'type' => 'question_set',
            'question_ids' => $this->autoGradableQuestionIds(),
            'assign_to_all' => '0',
            // Học sinh ngoài lớp bị lọc bỏ âm thầm.
            'student_ids' => [$a->id, $outsider->id],
        ]);

        $assignment = Assignment::firstOrFail();
        $this->assertSame([$a->id], $assignment->recipients()->pluck('student_id')->all());

        $this->actingAs($b)->get(route('student.assignments.show', $assignment))->assertForbidden();
        $this->actingAs($a)->get(route('student.assignments.show', $assignment))->assertOk();
    }

    public function test_essay_questions_are_not_attached_to_question_sets(): void
    {
        $class = $this->makeClass();
        $this->enroll($class, $this->makeStudent());
        $essay = Question::where('type', Question::TYPE_ESSAY)->value('id');

        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), [
            'class_id' => $class->id,
            'title' => 'Chỉ tự luận',
            'type' => 'question_set',
            'question_ids' => [$essay],
            'assign_to_all' => '1',
        ])->assertSessionHasErrors('question_ids');

        $this->assertSame(0, Assignment::count());
    }

    public function test_cannot_assign_to_class_you_do_not_teach(): void
    {
        $class = $this->makeClass($this->makeTeacher());
        $this->enroll($class, $this->makeStudent());

        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), [
            'class_id' => $class->id,
            'title' => 'Xâm nhập',
            'type' => 'question_set',
            'question_ids' => $this->autoGradableQuestionIds(),
            'assign_to_all' => '1',
        ])->assertForbidden();
    }

    public function test_assigning_to_empty_class_fails_cleanly(): void
    {
        $class = $this->makeClass();

        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), [
            'class_id' => $class->id,
            'title' => 'Không ai nhận',
            'type' => 'question_set',
            'question_ids' => $this->autoGradableQuestionIds(),
            'assign_to_all' => '1',
        ])->assertSessionHasErrors('student_ids');

        $this->assertSame(0, Assignment::count());
    }

    // --- Học sinh làm bộ câu hỏi --------------------------------------------------

    public function test_student_submits_question_set_and_is_completed(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $assignment = $this->questionSetAssignment($class);

        $this->actingAs($student)->get(route('student.assignments.index'))->assertOk()->assertSee('BTVN');

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), ['answers' => $this->perfectAnswers($assignment)])
            ->assertRedirect();

        $record = AssignmentStudent::firstOrFail();
        $this->assertSame(AssignmentStudent::STATUS_COMPLETED, $record->status);
        $this->assertSame(100, $record->percent);
        $this->assertFalse($record->is_late);
        $this->assertSame(1, $record->attempts_count);

        $this->assertSame(
            $assignment->questions()->count(),
            QuestionAttempt::where('context', 'assignment')->count(),
        );
    }

    public function test_no_retry_blocks_second_submission(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $assignment = $this->questionSetAssignment($class);

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), ['answers' => []]);
        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), ['answers' => $this->perfectAnswers($assignment)])
            ->assertSessionHas('error', 'Bạn đã nộp bài này.');

        $this->assertSame(0, AssignmentStudent::firstOrFail()->percent);
    }

    public function test_retry_keeps_best_score_and_respects_limit(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $assignment = $this->questionSetAssignment($class, ['allow_retry' => true, 'max_attempts' => 2]);

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), ['answers' => $this->perfectAnswers($assignment)]);
        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), ['answers' => []]);

        $record = AssignmentStudent::firstOrFail();
        $this->assertSame(2, $record->attempts_count);
        $this->assertSame(100, $record->percent, 'Lượt sau thấp hơn không được kéo điểm xuống.');

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), ['answers' => []])
            ->assertSessionHas('error', 'Bạn đã dùng hết 2 lượt nộp.');
    }

    public function test_late_submission_is_accepted_but_flagged(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $assignment = $this->questionSetAssignment($class, ['due_at' => now()->addHour()]);

        $this->travel(2)->hours();

        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), ['answers' => []]);

        $this->assertTrue(AssignmentStudent::firstOrFail()->is_late);
    }

    public function test_moving_due_date_recomputes_late_flag(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $assignment = $this->questionSetAssignment($class, ['due_at' => now()->addHour()]);

        $this->travel(2)->hours();
        $this->actingAs($student)->post(route('student.assignments.submit', $assignment), ['answers' => []]);
        $this->assertTrue(AssignmentStudent::firstOrFail()->is_late);

        // Giáo viên gia hạn → bài vừa nộp không còn tính là trễ.
        $this->actingAs($this->teacher)->put(route('teacher.assignments.update', $assignment), [
            'title' => $assignment->title,
            'due_at' => now()->addDay()->format('Y-m-d\TH:i'),
        ]);

        $this->assertFalse(AssignmentStudent::firstOrFail()->is_late);
    }

    public function test_closed_assignment_rejects_submissions(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $assignment = $this->questionSetAssignment($class);

        $this->actingAs($this->teacher)->post(route('teacher.assignments.close', $assignment));

        $this->actingAs($student)
            ->post(route('student.assignments.submit', $assignment), ['answers' => []])
            ->assertSessionHas('error', 'Giáo viên đã đóng bài này.');
    }

    public function test_student_cannot_view_another_students_submission(): void
    {
        $class = $this->makeClass();
        [$a, $b] = [$this->makeStudent(), $this->makeStudent()];
        $this->enroll($class, $a, $b);
        $assignment = $this->questionSetAssignment($class);

        $this->actingAs($a)->post(route('student.assignments.submit', $assignment), ['answers' => []]);
        $submission = \App\Models\AssignmentSubmission::firstOrFail();

        $this->actingAs($b)
            ->get(route('student.assignments.result', [$assignment, $submission]))
            ->assertNotFound();
    }

    // --- Bài dạng đề kiểm tra: đồng bộ qua event ----------------------------------

    private function publishedExamWithQuestions(bool $withEssay): Exam
    {
        $exam = Exam::create([
            'title' => 'Đề giao', 'slug' => 'de-giao-'.uniqid(), 'grade_id' => $this->grade->id,
            'duration_minutes' => 30, 'max_attempts' => 3, 'shuffle_questions' => false, 'shuffle_options' => false,
            'status' => 'published', 'created_by' => $this->teacher->id,
        ]);

        $ids = Question::where('grade_id', $this->grade->id)->published()
            ->when(! $withEssay, fn ($q) => $q->where('type', '!=', Question::TYPE_ESSAY))
            ->pluck('id');

        foreach ($ids as $i => $id) {
            $exam->questions()->attach($id, ['sort_order' => $i, 'points' => 1]);
        }

        return $exam;
    }

    public function test_exam_assignment_completes_when_student_finishes_exam(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $exam = $this->publishedExamWithQuestions(withEssay: false);

        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), [
            'class_id' => $class->id, 'title' => 'Làm đề', 'type' => 'exam',
            'exam_id' => $exam->id, 'assign_to_all' => '1',
        ]);

        $this->actingAs($student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        $this->actingAs($student)->post(route('student.exams.submit', $attempt));

        $record = AssignmentStudent::firstOrFail();
        $this->assertSame(AssignmentStudent::STATUS_COMPLETED, $record->status);
        $this->assertSame(0, $record->percent);
        $this->assertSame(1, $record->attempts_count);
    }

    public function test_exam_attempt_before_assignment_does_not_count(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $exam = $this->publishedExamWithQuestions(withEssay: false);

        $this->actingAs($student)->post(route('student.exams.start', $exam));
        $this->actingAs($student)->post(route('student.exams.submit', ExamAttempt::firstOrFail()));

        $this->travel(1)->minutes();

        app(\App\Services\Teaching\AssignmentService::class)->create($class, $this->teacher, [
            'title' => 'Giao sau', 'type' => 'exam', 'exam_id' => $exam->id,
            'assign_to_all' => true, 'allow_retry' => false,
        ]);

        $this->assertSame(AssignmentStudent::STATUS_ASSIGNED, AssignmentStudent::firstOrFail()->status);
    }

    public function test_exam_with_essay_moves_from_submitted_to_completed_after_grading(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $exam = $this->publishedExamWithQuestions(withEssay: true);

        app(\App\Services\Teaching\AssignmentService::class)->create($class, $this->teacher, [
            'title' => 'Đề có tự luận', 'type' => 'exam', 'exam_id' => $exam->id,
            'assign_to_all' => true, 'allow_retry' => false,
        ]);

        $this->actingAs($student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        $essayId = Question::where('type', Question::TYPE_ESSAY)->value('id');
        $this->actingAs($student)->postJson(route('student.exams.answer', $attempt), [
            'question_id' => $essayId, 'value' => 'Bài làm tự luận',
        ]);
        $this->actingAs($student)->post(route('student.exams.submit', $attempt));

        $this->assertSame(AssignmentStudent::STATUS_SUBMITTED, AssignmentStudent::firstOrFail()->status);

        $answer = StudentAnswer::where('question_id', $essayId)->firstOrFail();
        $this->actingAs($this->teacher)->post(route('teacher.exams.grade.answer', $answer), ['score' => 1]);

        $record = AssignmentStudent::firstOrFail();
        $this->assertSame(AssignmentStudent::STATUS_COMPLETED, $record->status);
        $this->assertNotNull($record->percent);
    }

    // --- Bài dạng học bài ----------------------------------------------------------

    public function test_lesson_assignment_completes_when_lesson_completed(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $lesson = Lesson::where('slug', 'cong-hai-phan-so-khac-mau-so')->firstOrFail();

        $this->actingAs($this->teacher)->post(route('teacher.assignments.store'), [
            'class_id' => $class->id, 'title' => 'Đọc bài', 'type' => 'lesson',
            'lesson_id' => $lesson->id, 'assign_to_all' => '1',
        ]);

        $this->assertSame(AssignmentStudent::STATUS_ASSIGNED, AssignmentStudent::firstOrFail()->status);

        $this->actingAs($student)->post(route('student.lesson.complete', $lesson));

        $this->assertSame(AssignmentStudent::STATUS_COMPLETED, AssignmentStudent::firstOrFail()->status);
    }

    public function test_lesson_completed_before_assignment_counts_immediately(): void
    {
        $class = $this->makeClass();
        $student = $this->makeStudent();
        $this->enroll($class, $student);
        $lesson = Lesson::where('slug', 'cong-hai-phan-so-khac-mau-so')->firstOrFail();

        app(ProgressService::class)->completeLesson($student, $lesson);

        app(\App\Services\Teaching\AssignmentService::class)->create($class, $this->teacher, [
            'title' => 'Đọc lại', 'type' => 'lesson', 'lesson_id' => $lesson->id,
            'assign_to_all' => true, 'due_at' => now()->addDay(), 'allow_retry' => false,
        ]);

        $record = AssignmentStudent::firstOrFail();
        $this->assertSame(AssignmentStudent::STATUS_COMPLETED, $record->status);
        $this->assertFalse($record->is_late);
    }

    // --- Theo dõi ---------------------------------------------------------------

    public function test_teacher_tracking_page_lists_done_and_not_done(): void
    {
        $class = $this->makeClass();
        $done = $this->makeStudent('Học Sinh Đã Nộp');
        $notDone = $this->makeStudent('Học Sinh Chưa Nộp');
        $this->enroll($class, $done, $notDone);
        $assignment = $this->questionSetAssignment($class);

        $this->actingAs($done)->post(route('student.assignments.submit', $assignment), ['answers' => $this->perfectAnswers($assignment)]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.assignments.show', $assignment))
            ->assertOk()
            ->assertSeeInOrder(['Học Sinh Chưa Nộp', 'Học Sinh Đã Nộp']) // chưa làm xếp trước
            ->assertSee('1/2');
    }

    public function test_other_teacher_cannot_view_tracking(): void
    {
        $class = $this->makeClass();
        $this->enroll($class, $this->makeStudent());
        $assignment = $this->questionSetAssignment($class);

        $this->actingAs($this->makeTeacher())
            ->get(route('teacher.assignments.show', $assignment))
            ->assertForbidden();
    }
}
