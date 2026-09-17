<?php

namespace App\Services\Teaching;

use App\Models\Question;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class QuestionService
{
    /** @param  array<string, mixed>  $data */
    public function create(array $data, User $author): Question
    {
        return DB::transaction(function () use ($data, $author) {
            $question = Question::create([
                'grade_id' => $data['grade_id'],
                'topic_id' => $data['topic_id'] ?? null,
                'type' => $data['type'],
                'content' => $data['content'],
                'explanation' => $data['explanation'] ?? null,
                'difficulty' => $data['difficulty'],
                'points' => $data['points'],
                'status' => $data['status'],
                'source' => $data['source'] ?? 'manual',
                'correct_answer' => $this->buildCorrectAnswer($data),
                'created_by' => $author->id,
            ]);

            $this->syncOptions($question, $data);

            return $question;
        });
    }

    /** @param  array<string, mixed>  $data */
    public function update(Question $question, array $data): Question
    {
        return DB::transaction(function () use ($question, $data) {
            $question->update([
                'grade_id' => $data['grade_id'],
                'topic_id' => $data['topic_id'] ?? null,
                'type' => $data['type'],
                'content' => $data['content'],
                'explanation' => $data['explanation'] ?? null,
                'difficulty' => $data['difficulty'],
                'points' => $data['points'],
                'status' => $data['status'],
                'correct_answer' => $this->buildCorrectAnswer($data),
            ]);

            $this->syncOptions($question, $data);

            return $question->refresh();
        });
    }

    /**
     * Đáp án của loại không dùng option được gói vào JSON `correct_answer`
     * theo đúng hình dạng GradingService mong đợi.
     *
     * @param  array<string, mixed>  $data
     */
    private function buildCorrectAnswer(array $data): ?array
    {
        return match ($data['type']) {
            Question::TYPE_TRUE_FALSE => ['value' => (bool) $data['true_false_value']],

            Question::TYPE_FILL_BLANK => [
                'blanks' => collect($data['blanks'] ?? [])
                    ->filter(fn ($line) => filled($line))
                    // "1/2 | 0,5" → ['1/2', '0,5'] — nhiều cách viết cùng một đáp án.
                    ->map(fn ($line) => collect(explode('|', $line))
                        ->map(fn ($v) => trim($v))
                        ->filter()
                        ->values()
                        ->all())
                    ->values()
                    ->all(),
            ],

            Question::TYPE_SHORT_ANSWER => [
                'accepted' => collect(explode('|', (string) ($data['accepted'] ?? '')))
                    ->map(fn ($v) => trim($v))
                    ->filter()
                    ->values()
                    ->all(),
            ],

            default => null,
        };
    }

    /** @param  array<string, mixed>  $data */
    private function syncOptions(Question $question, array $data): void
    {
        if (! in_array($data['type'], Question::CHOICE_TYPES, true)) {
            $question->options()->delete();

            return;
        }

        $question->options()->delete();

        $correct = array_map('strval', $data['correct_options'] ?? []);

        foreach (array_values($data['options'] ?? []) as $i => $option) {
            if (blank($option['content'] ?? null)) {
                continue;
            }

            $question->options()->create([
                'content' => $option['content'],
                // Form gửi về chỉ số dòng, không phải id (câu mới chưa có id).
                'is_correct' => in_array((string) $i, $correct, true),
                'sort_order' => $i + 1,
            ]);
        }
    }
}
