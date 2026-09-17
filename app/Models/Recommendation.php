<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Recommendation extends Model
{
    public const TYPE_REVIEW_LESSON = 'review_lesson';
    public const TYPE_PRACTICE_TOPIC = 'practice_topic';
    public const TYPE_TAKE_EXAM = 'take_exam';

    protected $fillable = [
        'user_id', 'type', 'topic_id', 'target_type', 'target_id', 'difficulty',
        'reason', 'priority', 'status', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', 'done')
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()));
    }
}
