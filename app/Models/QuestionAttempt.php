<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class QuestionAttempt extends Model
{
    public const CONTEXT_PRACTICE = 'practice';

    public const CONTEXT_EXAM = 'exam';

    public const CONTEXT_ASSIGNMENT = 'assignment';

    public const CONTEXT_PLACEMENT = 'placement';

    public const CONTEXT_SESSION_QUIZ = 'session_quiz';

    protected $fillable = [
        'user_id', 'question_id', 'topic_id', 'context', 'context_id',
        'difficulty', 'answer', 'is_correct', 'score', 'time_spent_seconds', 'attempt_no',
    ];

    protected function casts(): array
    {
        return [
            'answer' => 'array',
            'is_correct' => 'boolean',
            'score' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(Question::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }
}
