<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Một mốc hướng dẫn người mới của một người dùng (OB-03). */
class UserOnboarding extends Model
{
    protected $table = 'user_onboarding';

    protected $fillable = ['user_id', 'key', 'status', 'step'];

    protected function casts(): array
    {
        return ['step' => 'integer'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
