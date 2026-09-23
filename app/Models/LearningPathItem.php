<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class LearningPathItem extends Model
{
    public const TYPE_LESSON = 'lesson';

    public const TYPE_PRACTICE = 'practice';

    public const TYPE_EXAM = 'exam';

    protected $fillable = [
        'learning_path_stage_id', 'study_session_id', 'item_type', 'topic_id', 'target_id',
        'difficulty', 'origin', 'title', 'sort_order', 'status', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function stage(): BelongsTo
    {
        return $this->belongsTo(LearningPathStage::class, 'learning_path_stage_id');
    }

    public function session(): BelongsTo
    {
        return $this->belongsTo(StudySession::class, 'study_session_id');
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function isDone(): bool
    {
        return $this->status === 'done';
    }
}
