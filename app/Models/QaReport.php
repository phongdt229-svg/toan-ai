<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/** Một lượt báo xấu. Unique(reportable, user) ở DB chặn báo nhiều lần. */
class QaReport extends Model
{
    protected $fillable = ['reportable_type', 'reportable_id', 'user_id', 'reason'];

    public function reportable(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
