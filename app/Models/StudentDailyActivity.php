<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Tổng thời gian online / học thực của một học sinh trong một ngày (cộng từ heartbeat). */
class StudentDailyActivity extends Model
{
    protected $table = 'student_daily_activity';

    protected $fillable = [
        'user_id', 'activity_date', 'online_seconds', 'active_seconds',
        'first_seen_at', 'last_seen_at', 'last_active_at',
    ];

    protected function casts(): array
    {
        return [
            'activity_date' => 'date',
            'online_seconds' => 'integer',
            'active_seconds' => 'integer',
            'first_seen_at' => 'datetime',
            'last_seen_at' => 'datetime',
            'last_active_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function onlineMinutes(): int
    {
        return intdiv($this->online_seconds, 60);
    }

    public function activeMinutes(): int
    {
        return intdiv($this->active_seconds, 60);
    }
}
