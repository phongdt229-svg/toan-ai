<?php

namespace Tests\Feature\Learning;

use App\Models\Grade;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\StudentTopicMastery;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Database\Seeders\SampleQuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PracticeFlowTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class, GradeSeeder::class,
            SampleCurriculumSeeder::class, SampleQuestionSeeder::class,
        ]);

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $this->student->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        $this->topic = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();
    }

    /** Đáp án đúng cho mọi câu trong bộ — mô phỏng học sinh làm đúng hết. */
    private function perfectAnswers(array $questionIds): array
    {
        return Question::whereIn('id', $questionIds)->with('options')->get()
            ->mapWithKeys(fn (Question $q) => [$q->id => match ($q->type) {
                Question::TYPE_SINGLE_CHOICE => $q->options->firstWhere('is_correct', true)->id,
                Question::TYPE_MULTIPLE_CHOICE => $q->options->where('is_correct', true)->pluck('id')->all(),
                Question::TYPE_TRUE_FALSE => $q->correct_answer['value'] ? '1' : '0',
                Question::TYPE_FILL_BLANK => collect($q->correct_answer['blanks'])->map(fn ($b) => $b[0])->all(),
                Question::TYPE_SHORT_ANSWER => $q->correct_answer['accepted'][0],
                default => null,
            }])
            ->all();
    }

    public function test_practice_index_lists_topics_with_questions(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.practice.index'))
            ->assertOk()
            ->assertSee('Phép cộng phân số')
            // "So sánh phân số" không có câu hỏi nào nên không được hiện.
            ->assertDontSee('So sánh phân số');
    }

    public function test_essay_questions_are_excluded_from_practice_set(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.practice.start'), ['topic_id' => $this->topic->id, 'limit' => 20])
            ->assertRedirect(route('student.practice.show'));

        $ids = session('practice.current.question_ids');

        $this->assertNotEmpty($ids);
        $this->assertFalse(
            Question::whereIn('id', $ids)->where('type', Question::TYPE_ESSAY)->exists(),
        );
    }

    public function test_full_practice_flow_records_attempts_and_mastery(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.practice.start'), ['topic_id' => $this->topic->id, 'limit' => 20]);

        $ids = session('practice.current.question_ids');

        $this->actingAs($this->student)->get(route('student.practice.show'))->assertOk();

        $this->actingAs($this->student)
            ->post(route('student.practice.submit'), [
                'answers' => $this->perfectAnswers($ids),
                'time_spent' => array_fill_keys($ids, 20),
            ])
            ->assertRedirect(route('student.practice.result'));

        $this->assertSame(count($ids), QuestionAttempt::where('user_id', $this->student->id)->count());

        $mastery = StudentTopicMastery::where('user_id', $this->student->id)
            ->where('topic_id', $this->topic->id)
            ->firstOrFail();

        $this->assertSame(100, $mastery->mastery_score);
        $this->assertSame(count($ids), $mastery->correct_count);
        $this->assertSame(20, $mastery->avg_time_seconds);

        $this->actingAs($this->student)
            ->get(route('student.practice.result'))
            ->assertOk()
            ->assertSee('100%')
            ->assertSee('Giải thích');
    }

    public function test_answers_for_questions_outside_the_set_are_ignored(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.practice.start'), ['topic_id' => $this->topic->id, 'limit' => 5]);

        $ids = session('practice.current.question_ids');
        $outsider = Question::whereNotIn('id', $ids)->value('id');

        $this->actingAs($this->student)->post(route('student.practice.submit'), [
            'answers' => [$outsider => 'hack'],
        ]);

        // Chỉ ghi nhận đúng các câu server đã phát ra.
        $this->assertFalse(
            QuestionAttempt::where('user_id', $this->student->id)->where('question_id', $outsider)->exists(),
        );
        $this->assertSame(count($ids), QuestionAttempt::where('user_id', $this->student->id)->count());
    }

    public function test_wrong_answers_lower_mastery_and_attempt_no_increments(): void
    {
        foreach ([1, 2] as $round) {
            $this->actingAs($this->student)
                ->post(route('student.practice.start'), ['topic_id' => $this->topic->id, 'limit' => 20]);

            // Nộp trắng — sai toàn bộ.
            $this->actingAs($this->student)->post(route('student.practice.submit'), ['answers' => []]);
        }

        $mastery = StudentTopicMastery::where('user_id', $this->student->id)->firstOrFail();
        $this->assertSame(0, $mastery->mastery_score);

        $this->assertSame(2, QuestionAttempt::where('user_id', $this->student->id)->max('attempt_no'));
    }

    public function test_submit_without_session_redirects_back_to_index(): void
    {
        $this->actingAs($this->student)
            ->post(route('student.practice.submit'), ['answers' => []])
            ->assertRedirect(route('student.practice.index'));
    }
}
