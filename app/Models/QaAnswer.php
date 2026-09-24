<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class QaAnswer extends Model
{
    public const STATUS_VISIBLE = 'visible';

    public const STATUS_HIDDEN = 'hidden';

    protected $fillable = ['question_id', 'user_id', 'body', 'status'];

    protected function casts(): array
    {
        return ['reports_count' => 'integer'];
    }

    public function question(): BelongsTo
    {
        return $this->belongsTo(QaQuestion::class, 'question_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function reports(): MorphMany
    {
        return $this->morphMany(QaReport::class, 'reportable');
    }

    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_VISIBLE);
    }

    public function isHidden(): bool
    {
        return $this->status === self::STATUS_HIDDEN;
    }

    public function isBest(): bool
    {
        return $this->question?->best_answer_id === $this->id;
    }
}
