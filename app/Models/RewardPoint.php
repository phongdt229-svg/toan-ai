<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một dòng sổ cái điểm thưởng. Chỉ RewardService ghi. */
class RewardPoint extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'points', 'reason', 'source_type', 'source_id'];

    protected function casts(): array
    {
        return ['points' => 'integer', 'source_id' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
