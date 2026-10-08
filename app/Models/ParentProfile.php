<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ParentProfile extends Model
{
    protected $fillable = [
        'user_id', 'weekly_report_enabled', 'last_weekly_report_at',
        'session_events_enabled', 'daily_report_enabled', 'last_daily_report_at',
    ];

    protected function casts(): array
    {
        return [
            'weekly_report_enabled' => 'boolean',
            'last_weekly_report_at' => 'datetime',
            'session_events_enabled' => 'boolean',
            'daily_report_enabled' => 'boolean',
            'last_daily_report_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
