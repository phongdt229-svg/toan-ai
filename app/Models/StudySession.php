<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** Một "buổi học" (§36) — vài mục của lộ trình, kết thúc bằng kiểm tra cuối buổi (§37). */
class StudySession extends Model
{
    public const STATUS_PLANNED = 'planned';
    public const STATUS_QUIZ_PENDING = 'quiz_pending';
    public const STATUS_DONE = 'done';

    protected $fillable = [
        'user_id', 'learning_path_id', 'session_no', 'status', 'quiz_question_ids',
        'quiz_percent', 'quiz_submitted_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'quiz_question_ids' => 'array',
            'quiz_submitted_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LearningPathItem::class)->orderBy('sort_order');
    }

    public function isDone(): bool
    {
        return $this->status === self::STATUS_DONE;
    }
}
