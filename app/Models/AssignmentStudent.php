<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AssignmentStudent extends Model
{
    public const STATUS_ASSIGNED = 'assigned';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_COMPLETED = 'completed';

    protected $fillable = [
        'assignment_id', 'student_id', 'status', 'score', 'max_score', 'percent',
        'attempts_count', 'time_spent_seconds', 'is_late', 'completed_at', 'due_reminded_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'max_score' => 'decimal:2',
            'percent' => 'integer',
            'attempts_count' => 'integer',
            'time_spent_seconds' => 'integer',
            'is_late' => 'boolean',
            'completed_at' => 'datetime',
        ];
    }

    public function assignment(): BelongsTo
    {
        return $this->belongsTo(Assignment::class)->withTrashed();
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function isDone(): bool
    {
        return $this->status !== self::STATUS_ASSIGNED;
    }
}
