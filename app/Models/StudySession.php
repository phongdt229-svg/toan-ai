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
        'quiz_started_at', 'quiz_expires_at', 'quiz_percent', 'quiz_submitted_at',
        'quiz_auto_submitted', 'completed_at',
    ];

    /** Đồng hồ ở trình duyệt tự nộp lúc 00:00 — cho thêm chừng này giây để request kịp tới server. */
    public const GRACE_SECONDS = 30;

    protected function casts(): array
    {
        return [
            'quiz_question_ids' => 'array',
            'quiz_started_at' => 'datetime',
            'quiz_expires_at' => 'datetime',
            'quiz_submitted_at' => 'datetime',
            'quiz_auto_submitted' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function quizRemainingSeconds(): int
    {
        return $this->quiz_expires_at ? (int) max(0, now()->diffInSeconds($this->quiz_expires_at, false)) : 0;
    }

    public function quizIsOverdue(): bool
    {
        return $this->quiz_expires_at !== null
            && now()->greaterThan($this->quiz_expires_at->copy()->addSeconds(self::GRACE_SECONDS));
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
