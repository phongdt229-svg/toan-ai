<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExamAttempt extends Model
{
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_GRADED = 'graded';

    /**
     * Cho phép trễ vài giây do mạng: câu trả lời lưu ngay sát giờ vẫn được nhận.
     * Quá mức này thì server không nhận thêm gì nữa.
     */
    public const GRACE_SECONDS = 30;

    protected $fillable = [
        'exam_id', 'user_id', 'attempt_no', 'started_at', 'expires_at', 'submitted_at',
        'status', 'auto_submitted', 'score', 'total_points', 'correct_count',
        'question_order', 'option_order',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'auto_submitted' => 'boolean',
            'score' => 'decimal:2',
            'total_points' => 'decimal:2',
            'correct_count' => 'integer',
            'question_order' => 'array',
            'option_order' => 'array',
        ];
    }

    public function exam(): BelongsTo
    {
        return $this->belongsTo(Exam::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(StudentAnswer::class);
    }

    public function isInProgress(): bool
    {
        return $this->status === self::STATUS_IN_PROGRESS;
    }

    public function isFinished(): bool
    {
        return ! $this->isInProgress();
    }

    /** Đã hết giờ tính cả thời gian ân hạn. */
    public function isPastDeadline(): bool
    {
        return now()->greaterThan($this->expires_at->copy()->addSeconds(self::GRACE_SECONDS));
    }

    public function remainingSeconds(): int
    {
        return max(0, (int) now()->diffInSeconds($this->expires_at, false));
    }

    public function durationSeconds(): ?int
    {
        return $this->submitted_at
            ? (int) $this->started_at->diffInSeconds($this->submitted_at)
            : null;
    }

    public function percent(): ?int
    {
        if ($this->score === null || (float) $this->total_points <= 0) {
            return null;
        }

        return (int) round((float) $this->score / (float) $this->total_points * 100);
    }
}
