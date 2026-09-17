<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class LearningPathStage extends Model
{
    /** Bốn giai đoạn theo §35, đúng thứ tự. */
    public const STAGES = [
        'foundation' => 'Ôn lại nền tảng',
        'consolidation' => 'Củng cố kiến thức',
        'advanced' => 'Nâng cao',
        'exam_practice' => 'Luyện đề',
    ];

    protected $fillable = ['learning_path_id', 'stage', 'name', 'sort_order', 'status', 'progress_percent'];

    public function path(): BelongsTo
    {
        return $this->belongsTo(LearningPath::class, 'learning_path_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(LearningPathItem::class)->orderBy('sort_order');
    }
}
