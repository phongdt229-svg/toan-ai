<?php

namespace App\Services\Learning;

use App\Models\Question;
use Illuminate\Support\Collection;

/**
 * Chấm tự động 5 loại câu hỏi; tự luận (essay) trả về "chờ người chấm" (§15).
 *
 * Nguyên tắc: đáp án đúng chỉ đọc từ DB, không bao giờ nhận từ client.
 */
class GradingService
{
    /**
     * @param  mixed  $answer  dữ liệu học sinh gửi lên (id option, chuỗi, mảng chuỗi…)
     */
    public function grade(Question $question, mixed $answer): GradingResult
    {
        $points = (float) $question->points;

        return match ($question->type) {
            Question::TYPE_SINGLE_CHOICE => $this->gradeSingleChoice($question, $answer, $points),
            Question::TYPE_MULTIPLE_CHOICE => $this->gradeMultipleChoice($question, $answer, $points),
            Question::TYPE_TRUE_FALSE => $this->gradeTrueFalse($question, $answer, $points),
            Question::TYPE_FILL_BLANK => $this->gradeFillBlank($question, $answer, $points),
            Question::TYPE_SHORT_ANSWER => $this->gradeShortAnswer($question, $answer, $points),
            Question::TYPE_ESSAY => GradingResult::pending($points),
            default => GradingResult::wrong($points),
        };
    }

    private function gradeSingleChoice(Question $question, mixed $answer, float $points): GradingResult
    {
        $selected = is_array($answer) ? ($answer[0] ?? null) : $answer;

        if ($selected === null || $selected === '') {
            return GradingResult::wrong($points);
        }

        $correctId = $question->options->firstWhere('is_correct', true)?->id;

        return (int) $selected === (int) $correctId
            ? GradingResult::correct($points)
            : GradingResult::wrong($points);
    }

    /**
     * Nhiều đáp án đúng: điểm từng phần theo công thức
     * (số chọn đúng − số chọn sai) / tổng số đáp án đúng, không âm.
     * Chọn thừa bị trừ để không thể "chọn hết cho chắc".
     */
    private function gradeMultipleChoice(Question $question, mixed $answer, float $points): GradingResult
    {
        $selected = collect(is_array($answer) ? $answer : [$answer])
            ->filter(fn ($v) => $v !== null && $v !== '')
            ->map(fn ($v) => (int) $v)
            ->unique();

        $correctIds = $question->options->where('is_correct', true)->pluck('id')->map(fn ($v) => (int) $v);

        if ($correctIds->isEmpty()) {
            return GradingResult::wrong($points);
        }

        $hits = $selected->intersect($correctIds)->count();
        $misses = $selected->diff($correctIds)->count();

        $ratio = max(0, ($hits - $misses) / $correctIds->count());

        return GradingResult::partial($ratio * $points, $points);
    }

    private function gradeTrueFalse(Question $question, mixed $answer, float $points): GradingResult
    {
        $expected = $this->truthy($question->correct_answer['value'] ?? null);
        $given = $this->truthy(is_array($answer) ? ($answer[0] ?? null) : $answer);

        if ($expected === null || $given === null) {
            return GradingResult::wrong($points);
        }

        return $expected === $given
            ? GradingResult::correct($points)
            : GradingResult::wrong($points);
    }

    /**
     * Điền chỗ trống: `correct_answer.blanks` là mảng, mỗi phần tử là danh sách
     * đáp án chấp nhận được cho chỗ trống thứ i. Điểm chia đều cho các chỗ trống.
     */
    private function gradeFillBlank(Question $question, mixed $answer, float $points): GradingResult
    {
        $blanks = collect($question->correct_answer['blanks'] ?? []);

        if ($blanks->isEmpty()) {
            return GradingResult::wrong($points);
        }

        $given = collect(is_array($answer) ? $answer : [$answer])->values();

        $hits = $blanks->filter(function ($accepted, $i) use ($given) {
            return $this->matchesAny($given->get($i), $accepted);
        })->count();

        return GradingResult::partial($hits / $blanks->count() * $points, $points);
    }

    private function gradeShortAnswer(Question $question, mixed $answer, float $points): GradingResult
    {
        $accepted = $question->correct_answer['accepted'] ?? [];
        $given = is_array($answer) ? ($answer[0] ?? null) : $answer;

        return $this->matchesAny($given, $accepted)
            ? GradingResult::correct($points)
            : GradingResult::wrong($points);
    }

    /** @param  mixed  $accepted  danh sách đáp án chấp nhận được */
    private function matchesAny(mixed $given, mixed $accepted): bool
    {
        if ($given === null || $given === '') {
            return false;
        }

        $normalized = $this->normalize((string) $given);

        return collect(is_array($accepted) ? $accepted : [$accepted])
            ->contains(fn ($a) => $this->normalize((string) $a) === $normalized);
    }

    /**
     * So khớp đáp án text phải bỏ qua khác biệt vô nghĩa:
     * hoa/thường, khoảng trắng thừa, và dấu phẩy thập phân kiểu Việt ("0,5" = "0.5").
     */
    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/\s+/u', '', $value) ?? $value;

        // Chỉ đổi dấu phẩy thành dấu chấm khi chuỗi là một số thập phân.
        if (preg_match('/^-?\d+,\d+$/', $value)) {
            $value = str_replace(',', '.', $value);
        }

        return $value;
    }

    private function truthy(mixed $value): ?bool
    {
        return match (true) {
            $value === true, $value === 1, $value === '1', $value === 'true' => true,
            $value === false, $value === 0, $value === '0', $value === 'false' => false,
            default => null,
        };
    }

    /**
     * Chấm cả bộ câu hỏi.
     *
     * @param  Collection<int, Question>  $questions
     * @param  array<int, mixed>  $answers  question_id => answer
     * @return array{results: array<int, GradingResult>, score: float, max_score: float, correct: int, pending: int}
     */
    public function gradeMany(Collection $questions, array $answers): array
    {
        $results = [];
        $score = 0.0;
        $maxScore = 0.0;
        $correct = 0;
        $pending = 0;

        foreach ($questions as $question) {
            $result = $this->grade($question, $answers[$question->id] ?? null);

            $results[$question->id] = $result;
            $score += $result->score;
            $maxScore += $result->maxScore;

            if ($result->isCorrect === true) {
                $correct++;
            } elseif ($result->needsManualGrading) {
                $pending++;
            }
        }

        return [
            'results' => $results,
            'score' => round($score, 2),
            'max_score' => round($maxScore, 2),
            'correct' => $correct,
            'pending' => $pending,
        ];
    }
}
