<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Grade;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\Learning\MockExamService;

/** Thi thử vào 10 / THPT: loại đề riêng, điểm thang 10, xếp hạng giữa thí sinh, chủ đề yếu. */
class MockExamTest extends ExamTestCase
{
    private function payload(array $extra = []): array
    {
        return [
            'title' => 'Thi thử vào 10 — đợt 1', 'grade_id' => $this->grade->id, 'type' => 'mock',
            'duration_minutes' => 90, 'difficulty' => 'mixed', 'access_level' => 'free', 'max_attempts' => 2,
            ...$extra,
        ];
    }

    private function mockExam(array $attrs = []): Exam
    {
        return $this->publishedExam(['type' => 'mock', 'mock_track' => 'grade10', ...$attrs]);
    }

    private function finished(Exam $exam, User $student, float $score, int $no = 1): ExamAttempt
    {
        return ExamAttempt::create([
            'exam_id' => $exam->id, 'user_id' => $student->id, 'attempt_no' => $no,
            'started_at' => now()->subHour(), 'expires_at' => now()->addHour(), 'submitted_at' => now(),
            'status' => ExamAttempt::STATUS_GRADED, 'score' => $score, 'total_points' => $exam->total_points,
            'correct_count' => 0, 'question_order' => [], 'option_order' => [],
        ]);
    }

    public function test_teacher_must_pick_a_track_for_mock_exams(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.exams.store'), $this->payload())
            ->assertSessionHasErrors('mock_track');

        $this->actingAs($this->teacher)->post(route('teacher.exams.store'), $this->payload(['mock_track' => 'grade10']))
            ->assertSessionHasNoErrors();
        $this->assertSame('grade10', Exam::firstOrFail()->mock_track);

        // Đề thường thì kỳ thi bị bỏ đi dù form có gửi.
        $this->actingAs($this->teacher)->post(route('teacher.exams.store'), $this->payload(['title' => 'Đề thường', 'type' => 'test', 'mock_track' => 'thpt']));
        $this->assertNull(Exam::where('title', 'Đề thường')->value('mock_track'));
    }

    public function test_mock_exams_have_their_own_page(): void
    {
        $mock = $this->mockExam(['title' => 'Đề thi thử ABC']);
        $normal = $this->publishedExam(['title' => 'Đề kiểm tra XYZ']);

        $this->actingAs($this->student)->get(route('student.mock-exams.index'))
            ->assertOk()->assertSee('Đề thi thử ABC')->assertDontSee('Đề kiểm tra XYZ')->assertSee('Thi vào lớp 10');

        $this->actingAs($this->student)->get(route('student.exams.index'))
            ->assertOk()->assertSee('Đề kiểm tra XYZ')->assertDontSee('Đề thi thử ABC');
    }

    public function test_track_matching_the_grade_is_listed_first(): void
    {
        $this->mockExam(['title' => 'Đề vào 10', 'mock_track' => 'grade10']);
        $this->mockExam(['title' => 'Đề THPT', 'mock_track' => 'thpt']);

        $twelfth = $this->makeStudent();
        $twelfth->studentProfile->update(['grade_id' => Grade::where('level', 12)->value('id')]);

        $titles = app(MockExamService::class)->openFor($twelfth->fresh())->pluck('title')->all();
        $this->assertSame('Đề THPT', $titles[0]);
    }

    public function test_score_out_of_ten_rounds_to_quarter_points(): void
    {
        $service = app(MockExamService::class);

        $this->assertSame(7.5, $service->scoreOutOfTen(9, 12));
        $this->assertSame(6.75, $service->scoreOutOfTen(8.1, 12)); // 6,75
        $this->assertSame(10.0, $service->scoreOutOfTen(12, 12));
        $this->assertSame(0.0, $service->scoreOutOfTen(3, 0));
    }

    public function test_standing_uses_each_candidates_best_attempt(): void
    {
        $exam = $this->mockExam(); // 12 điểm
        $others = [$this->makeStudent(), $this->makeStudent(), $this->makeStudent()];
        $this->finished($exam, $others[0], 3);
        $this->finished($exam, $others[0], 11, 2); // lượt tốt nhất của bạn này là 11
        $this->finished($exam, $others[1], 6);
        $this->finished($exam, $others[2], 2);

        $mine = $this->finished($exam, $this->student, 9);
        $standing = app(MockExamService::class)->standing($mine->load('exam'));

        $this->assertSame(7.5, $standing['score10']);
        $this->assertSame(2, $standing['rank']);          // chỉ thua bạn 11 điểm
        $this->assertSame(4, $standing['candidates']);
        $this->assertSame(67, $standing['beat_percent']); // hơn 2/3 bạn còn lại
        $this->assertSame(5.75, $standing['average10']);  // (11+6+2+9)/4 = 7/12 → 5,83 → làm tròn 0,25 = 5,75
    }

    public function test_result_page_shows_standing_for_mock_only(): void
    {
        $exam = $this->mockExam();
        $attempt = $this->finished($exam, $this->student, 12);

        $this->actingAs($this->student)->get(route('student.exams.result', $attempt))
            ->assertOk()->assertSee('Điểm thang 10')->assertSee('thí sinh đầu tiên');

        $normal = $this->finished($this->publishedExam(), $this->student, 12);
        $this->actingAs($this->student)->get(route('student.exams.result', $normal))
            ->assertOk()->assertDontSee('Điểm thang 10');
    }

    public function test_weak_topics_link_to_their_lessons(): void
    {
        $exam = $this->mockExam();
        $attempt = $this->finished($exam, $this->student, 0);
        $question = $exam->questions()->whereNotNull('topic_id')->with('topic')->firstOrFail();
        StudentAnswer::create([
            'exam_attempt_id' => $attempt->id, 'question_id' => $question->id, 'answer' => null,
            'is_correct' => false, 'score' => 0, 'max_score' => 1,
        ]);

        $this->actingAs($this->student)->get(route('student.exams.result', $attempt))
            ->assertOk()
            ->assertSee('Nên ôn trước kỳ thi')
            ->assertSee(route('student.learn.topic', $question->topic_id), false)
            ->assertSee($question->topic->name);
    }

    public function test_paid_mock_exam_is_locked_for_free_students(): void
    {
        $this->mockExam(['title' => 'Đề Pro', 'access_level' => 'pro']);

        $this->actingAs($this->student)->get(route('student.mock-exams.index'))
            ->assertOk()->assertSee('Gói Pro');
    }
}
