<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class LearningPath extends Model
{
    public const STATUS_ACTIVE = 'active';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_ARCHIVED = 'archived';

    protected $fillable = [
        'user_id', 'grade_id', 'placement_test_id', 'status', 'items_per_session',
        'total_sessions', 'completed_sessions', 'progress_percent', 'generated_at',
    ];

    protected function casts(): array
    {
        return ['generated_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function grade(): BelongsTo
    {
        return $this->belongsTo(Grade::class);
    }

    public function placementTest(): BelongsTo
    {
        return $this->belongsTo(PlacementTest::class);
    }

    public function stages(): HasMany
    {
        return $this->hasMany(LearningPathStage::class)->orderBy('sort_order');
    }

    public function sessions(): HasMany
    {
        return $this->hasMany(StudySession::class)->orderBy('session_no');
    }

    public function items(): HasManyThrough
    {
        return $this->hasManyThrough(LearningPathItem::class, LearningPathStage::class);
    }

    public function remainingSessions(): int
    {
        return max(0, $this->total_sessions - $this->completed_sessions);
    }
}
