<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/** Một câu hỏi trong mục Hỏi đáp. Nội dung đã lọc HTML lúc lưu (QaService). */
class QaQuestion extends Model
{
    public const STATUS_OPEN = 'open';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUS_HIDDEN = 'hidden';

    public const STATUS_LABELS = [
        self::STATUS_OPEN => 'Chờ trả lời',
        self::STATUS_RESOLVED => 'Đã có lời giải',
        self::STATUS_HIDDEN => 'Đang ẩn',
    ];

    protected $fillable = ['user_id', 'topic_id', 'title', 'body', 'status', 'best_answer_id'];

    protected function casts(): array
    {
        return ['answers_count' => 'integer', 'reports_count' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function answers(): HasMany
    {
        return $this->hasMany(QaAnswer::class, 'question_id');
    }

    public function bestAnswer(): BelongsTo
    {
        return $this->belongsTo(QaAnswer::class, 'best_answer_id');
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(QaReport::class, 'reportable');
    }

    /** Câu hỏi đang ẩn không hiện trong danh sách chung. */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_HIDDEN);
    }

    public function isHidden(): bool
    {
        return $this->status === self::STATUS_HIDDEN;
    }

    public function isResolved(): bool
    {
        return $this->status === self::STATUS_RESOLVED;
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }
}
