<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StudentTopicMastery extends Model
{
    protected $table = 'student_topic_mastery';

    /** Dưới ngưỡng này coi là chủ đề yếu — dùng cho đề xuất ôn tập (§11, §14). */
    public const WEAK_THRESHOLD = 60;

    protected $fillable = [
        'user_id', 'topic_id', 'correct_count', 'wrong_count',
        'avg_time_seconds', 'mastery_score', 'last_practiced_at',
    ];

    protected function casts(): array
    {
        return ['last_practiced_at' => 'datetime'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function topic(): BelongsTo
    {
        return $this->belongsTo(Topic::class);
    }

    public function isWeak(): bool
    {
        return $this->mastery_score < self::WEAK_THRESHOLD;
    }
}
