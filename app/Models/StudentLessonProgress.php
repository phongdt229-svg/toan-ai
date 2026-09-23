<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentLessonProgress extends Model
{
    protected $table = 'student_lesson_progress';

    public const STATUS_NOT_STARTED = 'not_started';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'user_id', 'lesson_id', 'status', 'sections_completed',
        'progress_percent', 'time_spent_seconds', 'last_viewed_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sections_completed' => 'array',
            'progress_percent' => 'integer',
            'time_spent_seconds' => 'integer',
            'last_viewed_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(Lesson::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
