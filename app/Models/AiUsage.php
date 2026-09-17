<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiUsage extends Model
{
    protected $table = 'ai_usage';

    protected $fillable = [
        'user_id', 'usage_date', 'feature', 'request_count', 'failed_count',
        'tokens_in', 'tokens_out', 'cost_estimate',
    ];

    protected function casts(): array
    {
        return [
            'usage_date' => 'date',
            'cost_estimate' => 'decimal:6',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
