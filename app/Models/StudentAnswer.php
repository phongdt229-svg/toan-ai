<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentAnswer extends Model
{
    protected $fillable = [
        'exam_attempt_id', 'question_id', 'answer', 'is_correct', 'score', 'max_score',
        'time_spent_seconds', 'graded_by', 'feedback', 'graded_at',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'is_correct' => 'boolean',
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'graded_at' => 'datetime',
        ];
    }

    public function attempt(): BelongsTo
    {
        return $this->belongsTo(ExamAttempt::class, 'exam_attempt_id');
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class)->withTrashed();
    }

    public function grader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'graded_by');
    }

    /** Giá trị học sinh đã chọn/nhập — lưu bọc trong {"value": ...}. */
    public function value(): mixed
    {
        return $this->answer['value'] ?? null;
    }

    public function isPendingManualGrade(): bool
    {
        return $this->score === null;
    }
}
