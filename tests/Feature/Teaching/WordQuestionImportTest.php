<?php

namespace Tests\Feature\Teaching;

use App\Models\Grade;
use App\Models\Question;
use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use App\Services\Teaching\DocxQuestionParser;
use App\Support\Docx\SimpleDocx;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Nhập đề Word (.docx): tách câu, công thức Equation → LaTeX, xem trước rồi mới lưu. */
class WordQuestionImportTest extends TestCase
{
    use RefreshDatabase;

    private const FRAC = '<m:f><m:num><m:r><m:t>1</m:t></m:r></m:num><m:den><m:r><m:t>2</m:t></m:r></m:den></m:f>';

    private User $teacher;

    private Grade $grade;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, SampleCurriculumSeeder::class]);

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole(Role::TEACHER);
        $this->grade = Grade::where('level', 6)->firstOrFail();
        $this->topic = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();
    }

    /** @param  array<int, mixed>  $paragraphs */
    private function docx(array $paragraphs, string $name = 'de.docx'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'test').'.docx';
        file_put_contents($path, SimpleDocx::build($paragraphs));

        return new UploadedFile($path, $name, 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', null, true);
    }

    /** @param  array<int, mixed>  $paragraphs */
    private function parse(array $paragraphs): array
    {
        return app(DocxQuestionParser::class)->parse($this->docx($paragraphs)->getRealPath())['rows'];
    }

    private function sampleExam(): array
    {
        return [
            'ĐỀ KIỂM TRA 15 PHÚT — LỚP 6',
            ['Câu 1: Tính ', ['math' => self::FRAC], ' + ', ['math' => self::FRAC], ' với x < 3'],
            ['A. 0', ['tab' => true], 'B. ', ['math' => self::FRAC], ['tab' => true], 'C. 1', ['tab' => true], 'D. 2'],
            'Đáp án: C',
            'Lời giải: Cộng hai nửa được một.',
            'Câu 2. Giải hệ phương trình',
            'A. (2;1)    B. (1;2)',
            ['C. (3;0)    ', ['u' => 'D.'], ' (0;3)'],
            'Câu 3 (0,5 điểm): 2 là số nguyên tố.',
            'Đáp án: Đúng',
            'Câu 4: Tính 2 + 3',
            'Đáp án: 5 | năm',
            'Mức độ: Khó',
            'Câu 5: Chứng minh tam giác cân.',
            'Câu 6: Câu thiếu đáp án đúng',
            'A. 1    B. 2',
        ];
    }

    public function test_parser_reads_every_question_type(): void
    {
        $rows = collect($this->parse($this->sampleExam()))->keyBy('number');

        $this->assertCount(6, $rows);

        $this->assertSame(Question::TYPE_SINGLE_CHOICE, $rows[1]['type']);
        $this->assertSame('3', $rows[1]['correct']);
        $this->assertStringContainsString('$\frac{1}{2}$', $rows[1]['content']);
        $this->assertStringContainsString('x &lt; 3', $rows[1]['content']); // chữ được escape, không thành thẻ HTML
        $this->assertSame('$\frac{1}{2}$', $rows[1]['option_2']);
        $this->assertSame('Cộng hai nửa được một.', $rows[1]['explanation']);

        // "Giải hệ…" là nội dung câu hỏi, không phải dòng "Giải:"; đáp án gạch chân là đáp án đúng.
        $this->assertSame('Giải hệ phương trình', $rows[2]['content']);
        $this->assertSame('4', $rows[2]['correct']);

        $this->assertSame(Question::TYPE_TRUE_FALSE, $rows[3]['type']);
        $this->assertSame('true', $rows[3]['correct']);
        $this->assertSame('0.5', $rows[3]['points']);
        $this->assertSame('2 là số nguyên tố.', $rows[3]['content']);

        $this->assertSame(Question::TYPE_SHORT_ANSWER, $rows[4]['type']);
        $this->assertSame('5|năm', $rows[4]['accepted']);
        $this->assertSame('hard', $rows[4]['difficulty']);

        $this->assertSame(Question::TYPE_ESSAY, $rows[5]['type']);
        $this->assertNotEmpty($rows[5]['warnings']);

        $this->assertNotEmpty($rows[6]['errors']);
    }

    public function test_formulas_cover_roots_systems_and_powers(): void
    {
        $sqrt = '<m:rad><m:radPr><m:degHide m:val="1"/></m:radPr><m:deg/><m:e><m:r><m:t>x</m:t></m:r></m:e></m:rad>';
        $cube = '<m:rad><m:deg><m:r><m:t>3</m:t></m:r></m:deg><m:e><m:r><m:t>8</m:t></m:r></m:e></m:rad>';
        $power = '<m:sSup><m:e><m:r><m:t>x</m:t></m:r></m:e><m:sup><m:r><m:t>2</m:t></m:r></m:sup></m:sSup>';
        $system = '<m:d><m:dPr><m:begChr m:val="{"/><m:endChr m:val=""/></m:dPr><m:e><m:eqArr>'
            .'<m:e><m:r><m:t>x+y=3</m:t></m:r></m:e><m:e><m:r><m:t>x−y≤1</m:t></m:r></m:e></m:eqArr></m:e></m:d>';

        $rows = $this->parse([
            ['Câu 1: ', ['math' => $sqrt], ' ', ['math' => $cube], ' ', ['math' => $power], ' ', ['math' => $system]],
            'Đáp án: 2',
        ]);

        $content = html_entity_decode($rows[0]['content']);
        $this->assertStringContainsString('$\sqrt{x}$', $content);
        $this->assertStringContainsString('$\sqrt[3]{8}$', $content);
        $this->assertStringContainsString('$x^{2}$', $content);
        $this->assertStringContainsString('\begin{cases}x+y=3 \\\\ x-y\le 1\end{cases}', $content);
    }

    public function test_upload_previews_then_confirm_saves_questions_once(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('teacher.questions.import.word'), [
            'grade_id' => $this->grade->id,
            'topic_id' => $this->topic->id,
            'file' => $this->docx($this->sampleExam()),
        ]);

        $response->assertRedirect();
        $this->assertSame(0, Question::count()); // chưa lưu gì trước khi xác nhận

        $previewUrl = $response->headers->get('Location');
        $this->actingAs($this->teacher)->get($previewUrl)
            ->assertOk()
            ->assertSee('Nhập 5 câu hỏi')
            ->assertSee('1 câu lỗi sẽ bị bỏ qua')
            ->assertSee('chưa có đáp án đúng');

        $confirmUrl = str_replace(url('/'), '', $previewUrl);
        $this->actingAs($this->teacher)->post($confirmUrl, ['status' => 'draft'])
            ->assertRedirect(route('teacher.questions.index'))
            ->assertSessionHas('status');

        $this->assertSame(5, Question::count());
        $first = Question::with('options')->where('type', Question::TYPE_SINGLE_CHOICE)->orderBy('id')->firstOrFail();
        $this->assertSame($this->topic->id, $first->topic_id);
        $this->assertSame('draft', $first->status);
        $this->assertTrue($first->options->firstWhere('sort_order', 3)->is_correct);
        $this->assertSame('0.50', (string) Question::where('type', Question::TYPE_TRUE_FALSE)->value('points'));

        // Bấm xác nhận lần hai → không nhập trùng.
        $this->actingAs($this->teacher)->post($confirmUrl, ['status' => 'draft'])
            ->assertRedirect(route('teacher.questions.import'))
            ->assertSessionHas('error');
        $this->assertSame(5, Question::count());
    }

    public function test_preview_token_belongs_to_the_uploader_only(): void
    {
        $response = $this->actingAs($this->teacher)->post(route('teacher.questions.import.word'), [
            'grade_id' => $this->grade->id,
            'file' => $this->docx($this->sampleExam()),
        ]);
        $url = $response->headers->get('Location');

        $other = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $other->assignRole(Role::TEACHER);

        $this->actingAs($other)->get($url)->assertRedirect(route('teacher.questions.import'));
        $this->actingAs($other)->post(str_replace(url('/'), '', $url), ['status' => 'published']);
        $this->assertSame(0, Question::count());
    }

    public function test_topic_from_another_grade_is_rejected(): void
    {
        $otherGrade = Grade::where('level', 7)->firstOrFail();

        $this->actingAs($this->teacher)->post(route('teacher.questions.import.word'), [
            'grade_id' => $otherGrade->id,
            'topic_id' => $this->topic->id,
            'file' => $this->docx($this->sampleExam()),
        ])->assertSessionHasErrors('topic_id');
    }

    public function test_broken_or_wrong_file_reports_a_readable_error(): void
    {
        $fake = UploadedFile::fake()->createWithContent('de.docx', 'không phải zip');

        $this->actingAs($this->teacher)->post(route('teacher.questions.import.word'), [
            'grade_id' => $this->grade->id,
            'file' => $fake,
        ])->assertSessionHasErrors('file');
    }

    public function test_students_cannot_import(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole(Role::STUDENT);

        $this->actingAs($student)->post(route('teacher.questions.import.word'), [
            'grade_id' => $this->grade->id,
            'file' => $this->docx($this->sampleExam()),
        ])->assertForbidden();
    }

    public function test_template_is_a_docx_the_parser_understands(): void
    {
        $response = $this->actingAs($this->teacher)->get(route('teacher.questions.import.word.template'))->assertOk();

        $path = tempnam(sys_get_temp_dir(), 'tpl');
        file_put_contents($path, $response->getContent());
        $rows = app(DocxQuestionParser::class)->parse($path)['rows'];

        $this->assertCount(5, $rows);
        $this->assertSame([], array_merge(...array_column($rows, 'errors')));
        $this->assertSame('2', $rows[0]['correct']);
        $this->assertSame('2', $rows[1]['correct']); // đáp án gạch chân
    }

    public function test_import_page_offers_the_word_form(): void
    {
        $this->actingAs($this->teacher)->get(route('teacher.questions.import'))
            ->assertOk()
            ->assertSee('Nhập đề từ tệp Word')
            ->assertSee(route('teacher.questions.import.word.template'));
    }
}
