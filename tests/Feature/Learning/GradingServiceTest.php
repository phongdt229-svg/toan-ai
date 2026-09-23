<?php

namespace Tests\Feature\Learning;

use App\Models\Grade;
use App\Models\Question;
use App\Services\Learning\GradingService;
use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GradingServiceTest extends TestCase
{
    use RefreshDatabase;

    private GradingService $grading;

    private int $gradeId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GradeSeeder::class);
        $this->grading = app(GradingService::class);
        $this->gradeId = Grade::where('level', 6)->value('id');
    }

    /**
     * @param  list<array{0: string, 1: bool}>  $options
     */
    private function question(string $type, array $attrs = [], array $options = []): Question
    {
        $q = Question::create([
            'grade_id' => $this->gradeId,
            'type' => $type,
            'content' => '<p>Câu hỏi</p>',
            'difficulty' => 'medium',
            'points' => 2,
            'status' => 'published',
            ...$attrs,
        ]);

        foreach ($options as $i => [$content, $correct]) {
            $q->options()->create(['content' => $content, 'is_correct' => $correct, 'sort_order' => $i]);
        }

        return $q->load('options');
    }

    // --- Một đáp án ----------------------------------------------------------

    public function test_single_choice_correct_and_wrong(): void
    {
        $q = $this->question(Question::TYPE_SINGLE_CHOICE, [], [['A', false], ['B', true], ['C', false]]);
        $right = $q->options->firstWhere('is_correct', true)->id;
        $wrong = $q->options->firstWhere('is_correct', false)->id;

        $this->assertTrue($this->grading->grade($q, $right)->isCorrect);
        $this->assertSame(2.0, $this->grading->grade($q, (string) $right)->score);

        $this->assertFalse($this->grading->grade($q, $wrong)->isCorrect);
        $this->assertSame(0.0, $this->grading->grade($q, null)->score);
    }

    // --- Nhiều đáp án --------------------------------------------------------

    public function test_multiple_choice_full_credit_requires_all_correct_and_no_extras(): void
    {
        $q = $this->question(Question::TYPE_MULTIPLE_CHOICE, [], [
            ['A', true], ['B', true], ['C', false], ['D', false],
        ]);
        [$a, $b, $c] = $q->options->pluck('id')->all();

        $full = $this->grading->grade($q, [$a, $b]);
        $this->assertTrue($full->isCorrect);
        $this->assertSame(2.0, $full->score);

        // Chọn đúng 1/2 → nửa điểm, chưa tính là đúng.
        $half = $this->grading->grade($q, [$a]);
        $this->assertFalse($half->isCorrect);
        $this->assertSame(1.0, $half->score);
    }

    public function test_multiple_choice_selecting_everything_is_penalized(): void
    {
        $q = $this->question(Question::TYPE_MULTIPLE_CHOICE, [], [
            ['A', true], ['B', true], ['C', false], ['D', false],
        ]);

        // Chọn hết 4 ô: 2 đúng − 2 sai = 0 → không được điểm "ăn may".
        $result = $this->grading->grade($q, $q->options->pluck('id')->all());

        $this->assertFalse($result->isCorrect);
        $this->assertSame(0.0, $result->score);
    }

    // --- Đúng / Sai ----------------------------------------------------------

    public function test_true_false_accepts_common_encodings(): void
    {
        $q = $this->question(Question::TYPE_TRUE_FALSE, ['correct_answer' => ['value' => false]]);

        $this->assertTrue($this->grading->grade($q, '0')->isCorrect);
        $this->assertTrue($this->grading->grade($q, 'false')->isCorrect);
        $this->assertFalse($this->grading->grade($q, '1')->isCorrect);
        // Không trả lời thì không được coi là "Sai" đúng.
        $this->assertFalse($this->grading->grade($q, null)->isCorrect);
    }

    // --- Điền chỗ trống ------------------------------------------------------

    public function test_fill_blank_partial_credit_per_blank(): void
    {
        $q = $this->question(Question::TYPE_FILL_BLANK, [
            'correct_answer' => ['blanks' => [['3'], ['2']]],
        ]);

        $this->assertTrue($this->grading->grade($q, ['3', '2'])->isCorrect);

        $half = $this->grading->grade($q, ['3', '5']);
        $this->assertFalse($half->isCorrect);
        $this->assertSame(1.0, $half->score);
    }

    public function test_fill_blank_accepts_alternative_forms_and_vietnamese_decimal(): void
    {
        $q = $this->question(Question::TYPE_FILL_BLANK, [
            'correct_answer' => ['blanks' => [['1/2', '0.5']]],
        ]);

        $this->assertTrue($this->grading->grade($q, ['0,5'])->isCorrect);
        $this->assertTrue($this->grading->grade($q, [' 1 / 2 '])->isCorrect);
    }

    // --- Trả lời ngắn --------------------------------------------------------

    public function test_short_answer_ignores_case_and_whitespace(): void
    {
        $q = $this->question(Question::TYPE_SHORT_ANSWER, [
            'correct_answer' => ['accepted' => ['5/12']],
        ]);

        $this->assertTrue($this->grading->grade($q, '5 / 12')->isCorrect);
        $this->assertFalse($this->grading->grade($q, '5/6')->isCorrect);
        $this->assertFalse($this->grading->grade($q, '')->isCorrect);
    }

    public function test_comma_is_not_converted_outside_decimal_numbers(): void
    {
        // "1,2" trong ngữ cảnh liệt kê không được biến thành "1.2".
        $q = $this->question(Question::TYPE_SHORT_ANSWER, [
            'correct_answer' => ['accepted' => ['x=1,y=2']],
        ]);

        $this->assertTrue($this->grading->grade($q, 'x = 1, y = 2')->isCorrect);
    }

    // --- Tự luận -------------------------------------------------------------

    public function test_essay_is_pending_manual_grading(): void
    {
        $q = $this->question(Question::TYPE_ESSAY);

        $result = $this->grading->grade($q, 'Bài làm dài…');

        $this->assertNull($result->isCorrect);
        $this->assertTrue($result->needsManualGrading);
        $this->assertSame(0.0, $result->score);
        $this->assertSame(2.0, $result->maxScore);
    }

    // --- Chấm cả bộ ----------------------------------------------------------

    public function test_grade_many_aggregates_score_and_counts(): void
    {
        $single = $this->question(Question::TYPE_SINGLE_CHOICE, [], [['A', true], ['B', false]]);
        $tf = $this->question(Question::TYPE_TRUE_FALSE, ['correct_answer' => ['value' => true]]);
        $essay = $this->question(Question::TYPE_ESSAY);

        $summary = $this->grading->gradeMany(collect([$single, $tf, $essay]), [
            $single->id => $single->options->firstWhere('is_correct', true)->id,
            $tf->id => '0',
            $essay->id => 'bài làm',
        ]);

        $this->assertSame(2.0, $summary['score']);
        $this->assertSame(6.0, $summary['max_score']);
        $this->assertSame(1, $summary['correct']);
        $this->assertSame(1, $summary['pending']);
    }
}
