<?php

namespace Tests\Feature\Teaching;

use App\Models\Grade;
use App\Models\Question;
use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class QuestionBankTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Grade $grade;
    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, SampleCurriculumSeeder::class]);

        $this->teacher = $this->makeTeacher();
        $this->grade = Grade::where('level', 6)->firstOrFail();
        $this->topic = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();
    }

    private function makeTeacher(): User
    {
        $t = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $t->assignRole(Role::TEACHER);

        return $t;
    }

    private function base(array $extra = []): array
    {
        return [
            'grade_id' => $this->grade->id,
            'topic_id' => $this->topic->id,
            'content' => '<p>Câu hỏi thử</p>',
            'difficulty' => 'easy',
            'points' => 1,
            'status' => 'published',
            ...$extra,
        ];
    }

    public function test_create_single_choice_with_options(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.questions.store'), $this->base([
                'type' => Question::TYPE_SINGLE_CHOICE,
                'options' => [['content' => 'A'], ['content' => 'B'], ['content' => 'C']],
                'correct_options' => ['1'],
            ]))
            ->assertRedirect(route('teacher.questions.index'));

        $q = Question::with('options')->latest('id')->firstOrFail();

        $this->assertCount(3, $q->options);
        $this->assertSame('B', strip_tags($q->options->firstWhere('is_correct', true)->content));
        $this->assertSame($this->teacher->id, $q->created_by);
    }

    public function test_single_choice_rejects_two_correct_answers(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.questions.store'), $this->base([
                'type' => Question::TYPE_SINGLE_CHOICE,
                'options' => [['content' => 'A'], ['content' => 'B']],
                'correct_options' => ['0', '1'],
            ]))
            ->assertSessionHasErrors('correct_options');
    }

    public function test_choice_question_requires_two_options_and_a_correct_one(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.questions.store'), $this->base([
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'options' => [['content' => 'Chỉ một']],
                'correct_options' => [],
            ]))
            ->assertSessionHasErrors(['options', 'correct_options']);
    }

    public function test_fill_blank_stores_alternatives_per_blank(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.questions.store'), $this->base([
                'type' => Question::TYPE_FILL_BLANK,
                'blanks' => ['1/2 | 0,5', '6'],
            ]));

        $q = Question::latest('id')->firstOrFail();

        $this->assertSame([['1/2', '0,5'], ['6']], $q->correct_answer['blanks']);
    }

    public function test_changing_type_away_from_choice_removes_options(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.questions.store'), $this->base([
            'type' => Question::TYPE_SINGLE_CHOICE,
            'options' => [['content' => 'A'], ['content' => 'B']],
            'correct_options' => ['0'],
        ]));

        $q = Question::latest('id')->firstOrFail();

        $this->actingAs($this->teacher)->put(route('teacher.questions.update', $q), $this->base([
            'type' => Question::TYPE_SHORT_ANSWER,
            'accepted' => '42',
        ]));

        $q->refresh();
        $this->assertSame(Question::TYPE_SHORT_ANSWER, $q->type);
        $this->assertSame(0, $q->options()->count());
        $this->assertSame(['42'], $q->correct_answer['accepted']);
    }

    public function test_teacher_cannot_edit_another_teachers_question(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.questions.store'), $this->base([
            'type' => Question::TYPE_SHORT_ANSWER,
            'accepted' => '1',
        ]));
        $q = Question::latest('id')->firstOrFail();

        $other = $this->makeTeacher();

        $this->actingAs($other)->get(route('teacher.questions.edit', $q))->assertForbidden();
        $this->actingAs($other)->delete(route('teacher.questions.destroy', $q))->assertForbidden();
    }

    public function test_question_content_is_sanitized(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.questions.store'), $this->base([
            'type' => Question::TYPE_SHORT_ANSWER,
            'content' => '<p>Tính</p><script>alert(1)</script>',
            'accepted' => '1',
        ]));

        $this->assertStringNotContainsString('<script', Question::latest('id')->value('content'));
    }

    // --- Import CSV -----------------------------------------------------------

    private function csv(string $body): UploadedFile
    {
        $header = 'type,difficulty,points,content,explanation,topic_id,option_1,option_2,option_3,correct,accepted';

        return UploadedFile::fake()->createWithContent('q.csv', "\xEF\xBB\xBF{$header}\n{$body}");
    }

    public function test_csv_import_creates_all_supported_types(): void
    {
        $t = $this->topic->id;
        $body = implode("\n", [
            "single_choice,easy,1,\"Câu 1\",,{$t},A,B,C,2,",
            "multiple_choice,medium,2,\"Câu 2\",,{$t},A,B,C,\"1,3\",",
            "true_false,easy,1,\"Câu 3\",,{$t},,,,false,",
            "fill_blank,medium,2,\"Câu 4\",,{$t},,,,\"3 ; 2|hai\",",
            "short_answer,hard,1,\"Câu 5\",,{$t},,,,,5/12",
        ]);

        $this->actingAs($this->teacher)
            ->post(route('teacher.questions.import.store'), [
                'grade_id' => $this->grade->id,
                'status' => 'published',
                'file' => $this->csv($body),
            ])
            ->assertRedirect()
            ->assertSessionHas('status', 'Đã nhập 5 câu hỏi, bỏ qua 0 dòng.');

        $multi = Question::where('type', Question::TYPE_MULTIPLE_CHOICE)->with('options')->firstOrFail();
        $this->assertSame([1, 3], $multi->options->where('is_correct', true)->pluck('sort_order')->values()->all());

        $fill = Question::where('type', Question::TYPE_FILL_BLANK)->firstOrFail();
        $this->assertSame([['3'], ['2', 'hai']], $fill->correct_answer['blanks']);

        $tf = Question::where('type', Question::TYPE_TRUE_FALSE)->firstOrFail();
        $this->assertFalse($tf->correct_answer['value']);
    }

    public function test_csv_import_skips_bad_rows_and_reports_line_numbers(): void
    {
        $t = $this->topic->id;
        $body = implode("\n", [
            "single_choice,easy,1,\"Hợp lệ\",,{$t},A,B,,1,",
            "bogus_type,easy,1,\"Sai loại\",,{$t},,,,,",
            "single_choice,easy,1,\"Thiếu lựa chọn\",,{$t},A,,,1,",
            "short_answer,easy,1,\"Chủ đề sai lớp\",,999999,,,,,x",
        ]);

        $this->actingAs($this->teacher)->post(route('teacher.questions.import.store'), [
            'grade_id' => $this->grade->id,
            'status' => 'draft',
            'file' => $this->csv($body),
        ])->assertSessionHas('status', 'Đã nhập 1 câu hỏi, bỏ qua 3 dòng.');

        $errors = session('import_errors');
        $this->assertCount(3, $errors);
        $this->assertStringStartsWith('Dòng 3:', $errors[0]);

        // Dòng thiếu lựa chọn phải được rollback, không để lại câu hỏi mồ côi.
        $this->assertSame(1, Question::count());
    }

    public function test_import_template_downloads(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.questions.import.template'))
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }
}
