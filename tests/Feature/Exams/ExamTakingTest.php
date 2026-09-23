<?php

namespace Tests\Feature\Exams;

use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\StudentAnswer;
use App\Services\Learning\ExamService;

class ExamTakingTest extends ExamTestCase
{
    public function test_student_sees_open_exams_for_their_grade(): void
    {
        $exam = $this->publishedExam(['title' => 'Đề đang mở']);
        $this->publishedExam(['title' => 'Đề chưa mở', 'available_from' => now()->addDay()]);
        $this->publishedExam(['title' => 'Đề nháp', 'status' => 'draft']);

        $this->actingAs($this->student)
            ->get(route('student.exams.index'))
            ->assertOk()
            ->assertSee('Đề đang mở')
            ->assertDontSee('Đề chưa mở')
            ->assertDontSee('Đề nháp');

        $this->actingAs($this->student)->get(route('student.exams.show', $exam))->assertOk();
    }

    public function test_start_creates_attempt_with_server_side_deadline(): void
    {
        // Cột dateTime lưu tới giây — đóng băng ở mốc tròn giây để so sánh chính xác.
        $this->freezeSecond();
        $exam = $this->publishedExam(['duration_minutes' => 20]);

        $this->actingAs($this->student)
            ->post(route('student.exams.start', $exam))
            ->assertRedirect();

        $attempt = ExamAttempt::where('user_id', $this->student->id)->firstOrFail();

        $this->assertSame(ExamAttempt::STATUS_IN_PROGRESS, $attempt->status);
        $this->assertTrue($attempt->expires_at->equalTo(now()->addMinutes(20)));
        $this->assertCount(7, $attempt->question_order);
        $this->assertSame(12.0, (float) $attempt->total_points);
    }

    public function test_starting_twice_resumes_the_same_attempt(): void
    {
        $exam = $this->publishedExam();

        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));

        $this->assertSame(1, ExamAttempt::where('user_id', $this->student->id)->count());
    }

    public function test_cannot_exceed_max_attempts(): void
    {
        $exam = $this->publishedExam(['max_attempts' => 1]);

        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        $this->actingAs($this->student)
            ->from(route('student.exams.show', $exam))
            ->post(route('student.exams.start', $exam))
            ->assertSessionHas('error', 'Bạn đã dùng hết 1 lượt làm đề này.');

        $this->assertSame(1, ExamAttempt::count());
    }

    public function test_closed_exam_cannot_be_started(): void
    {
        $exam = $this->publishedExam(['available_to' => now()->subMinute()]);

        $this->actingAs($this->student)
            ->from(route('student.exams.index'))
            ->post(route('student.exams.start', $exam))
            ->assertSessionHas('error', 'Đề kiểm tra này hiện không mở.');
    }

    public function test_shuffled_order_is_fixed_for_the_attempt(): void
    {
        $exam = $this->publishedExam(['shuffle_questions' => true, 'shuffle_options' => true]);

        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();

        $this->assertEqualsCanonicalizing(
            $exam->questions()->pluck('questions.id')->all(),
            $attempt->question_order,
        );

        // Tải lại trang nhiều lần, thứ tự giữ nguyên như đã lưu.
        $service = app(ExamService::class);
        $first = $service->questionsForAttempt($attempt->fresh())->pluck('id')->all();
        $again = $service->questionsForAttempt($attempt->fresh())->pluck('id')->all();

        $this->assertSame($attempt->question_order, $first);
        $this->assertSame($first, $again);
    }

    public function test_autosave_then_submit_grades_everything(): void
    {
        $exam = $this->publishedExam();
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();

        foreach ($exam->questions as $q) {
            $this->actingAs($this->student)
                ->postJson(route('student.exams.answer', $attempt), [
                    'question_id' => $q->id,
                    'value' => $this->correctValue($q),
                    'time_spent' => 30,
                ])
                ->assertOk();
        }

        $this->actingAs($this->student)
            ->post(route('student.exams.submit', $attempt))
            ->assertRedirect(route('student.exams.result', $attempt));

        $attempt->refresh();

        // 6 câu tự chấm đúng hết (9 điểm), câu tự luận 3 điểm chờ chấm → trạng thái submitted.
        $this->assertSame(ExamAttempt::STATUS_SUBMITTED, $attempt->status);
        $this->assertSame(9.0, (float) $attempt->score);
        $this->assertSame(6, $attempt->correct_count);
        $this->assertFalse($attempt->auto_submitted);

        $this->assertSame(7, QuestionAttempt::where('context', 'exam')->where('context_id', $attempt->id)->count());

        $this->actingAs($this->student)
            ->get(route('student.exams.result', $attempt))
            ->assertOk()
            ->assertSee('chờ giáo viên chấm');
    }

    public function test_exam_without_essay_is_fully_graded_on_submit(): void
    {
        $exam = $this->publishedExam();
        $essay = Question::where('type', Question::TYPE_ESSAY)->firstOrFail();
        $exam->questions()->detach($essay->id);

        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        $this->assertSame(ExamAttempt::STATUS_GRADED, $attempt->fresh()->status);
    }

    public function test_blank_essay_scores_zero_without_manual_grading(): void
    {
        $exam = $this->publishedExam();

        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        // Nộp trắng toàn bộ, kể cả câu tự luận.
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        $attempt->refresh();
        $this->assertSame(ExamAttempt::STATUS_GRADED, $attempt->status);
        $this->assertSame(0.0, (float) $attempt->score);
    }

    public function test_answer_for_question_outside_attempt_is_rejected(): void
    {
        $exam = $this->publishedExam();

        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();

        $this->actingAs($this->student)
            ->postJson(route('student.exams.answer', $attempt), ['question_id' => 999999, 'value' => 'x'])
            ->assertStatus(409);
    }

    public function test_answers_after_deadline_are_rejected_and_attempt_auto_submits(): void
    {
        $exam = $this->publishedExam(['duration_minutes' => 10]);
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        $q = $exam->questions->first();

        // Trong thời gian ân hạn 30s vẫn nhận.
        $this->travel(10 * 60 + 20)->seconds();
        $this->actingAs($this->student)
            ->postJson(route('student.exams.answer', $attempt), ['question_id' => $q->id, 'value' => $this->correctValue($q)])
            ->assertOk();

        // Quá ân hạn → từ chối, bài được chốt.
        $this->travel(30)->seconds();
        $this->actingAs($this->student)
            ->postJson(route('student.exams.answer', $attempt), ['question_id' => $q->id, 'value' => 'đổi đáp án'])
            ->assertStatus(409)
            ->assertJsonPath('redirect', route('student.exams.result', $attempt));

        $attempt->refresh();
        $this->assertTrue($attempt->auto_submitted);
        $this->assertNotSame(ExamAttempt::STATUS_IN_PROGRESS, $attempt->status);
        // Thời điểm nộp ghi là lúc hết giờ, không phải lúc server phát hiện.
        $this->assertTrue($attempt->submitted_at->equalTo($attempt->expires_at));
        // Câu trả lời lưu trong ân hạn vẫn được chấm.
        $this->assertTrue(StudentAnswer::where('exam_attempt_id', $attempt->id)->where('question_id', $q->id)->value('is_correct'));
    }

    public function test_opening_take_page_after_deadline_finalizes(): void
    {
        $exam = $this->publishedExam(['duration_minutes' => 5]);
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();

        $this->travel(10)->minutes();

        $this->actingAs($this->student)
            ->get(route('student.exams.take', $attempt))
            ->assertRedirect(route('student.exams.result', $attempt));

        $this->assertTrue($attempt->fresh()->auto_submitted);
    }

    public function test_scheduled_command_finalizes_abandoned_attempts(): void
    {
        $exam = $this->publishedExam(['duration_minutes' => 5]);

        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $other = $this->makeStudent();
        $this->actingAs($other)->post(route('student.exams.start', $exam));

        $this->travel(6)->minutes();

        $this->artisan('exams:finalize-expired')
            ->expectsOutput('Đã tự nộp 2 lượt làm bài.')
            ->assertSuccessful();

        $this->assertSame(0, ExamAttempt::where('status', ExamAttempt::STATUS_IN_PROGRESS)->count());
    }

    public function test_double_submit_does_not_double_count(): void
    {
        $exam = $this->publishedExam();
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();

        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        $this->assertSame(7, QuestionAttempt::where('context_id', $attempt->id)->count());
    }

    public function test_student_cannot_access_another_students_attempt(): void
    {
        $exam = $this->publishedExam();
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();

        $intruder = $this->makeStudent();

        $this->actingAs($intruder)->get(route('student.exams.take', $attempt))->assertForbidden();
        $this->actingAs($intruder)->get(route('student.exams.result', $attempt))->assertForbidden();
        $this->actingAs($intruder)
            ->postJson(route('student.exams.answer', $attempt), ['question_id' => 1, 'value' => 'x'])
            ->assertForbidden();
        $this->actingAs($intruder)->post(route('student.exams.submit', $attempt))->assertForbidden();
    }

    public function test_answers_hidden_until_exam_closes(): void
    {
        $exam = $this->publishedExam(['available_to' => now()->addHour()]);
        $this->actingAs($this->student)->post(route('student.exams.start', $exam));
        $attempt = ExamAttempt::firstOrFail();
        $this->actingAs($this->student)->post(route('student.exams.submit', $attempt));

        $this->actingAs($this->student)
            ->get(route('student.exams.result', $attempt))
            ->assertOk()
            ->assertSee('sẽ hiện sau khi đề đóng')
            ->assertDontSee('Đáp án đúng:');

        $this->travel(2)->hours();

        $this->actingAs($this->student)
            ->get(route('student.exams.result', $attempt))
            ->assertSee('Đáp án đúng:');
    }
}
