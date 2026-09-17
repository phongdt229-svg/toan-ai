<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Bản chụp câu hỏi lúc ra đề. Chấm điểm bằng cách dựng lại một Question tạm từ bản chụp
 * để tái dùng nguyên GradingService và view nhập đáp án.
 */
class PlacementTestQuestion extends Model
{
    protected $fillable = [
        'placement_test_id', 'question_id', 'topic_id', 'type', 'difficulty',
        'content', 'options', 'correct_answer', 'explanation', 'points', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'options' => 'array',
            'correct_answer' => 'array',
            'points' => 'decimal:2',
        ];
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    /**
     * Question KHÔNG lưu DB, id = id bản chụp (để tên input answers[id] là duy nhất trong bài).
     * Lựa chọn giữ id gốc từ ngân hàng, hoặc id tuần tự với câu AI sinh.
     */
    public function toQuestion(): Question
    {
        $question = new Question;
        // setRawAttributes: bỏ qua mutator lọc HTML — nội dung đã được lọc khi ghi vào ngân hàng / lúc chụp.
        $question->setRawAttributes([
            'id' => $this->id,
            'grade_id' => null,
            'topic_id' => $this->topic_id,
            'type' => $this->type,
            'difficulty' => $this->difficulty,
            'content' => $this->content,
            'explanation' => $this->explanation,
            'points' => $this->points,
            'correct_answer' => $this->correct_answer === null ? null : json_encode($this->correct_answer),
            'status' => 'published',
        ]);

        $question->setRelation('options', collect($this->options ?? [])->map(function (array $o, int $i) {
            $option = new QuestionOption;
            $option->setRawAttributes([
                'id' => $o['id'],
                'content' => $o['content'],
                'is_correct' => $o['is_correct'] ? 1 : 0,
                'sort_order' => $i + 1,
            ]);

            return $option;
        }));

        return $question;
    }
}
