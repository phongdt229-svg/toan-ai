<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Điểm danh một buổi theo lịch học (TA-09). */
class StudentAttendance extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_LATE = 'late';

    public const STATUS_ABSENT_PENDING = 'absent_pending';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PRESENT = 'present';

    public const STATUS_PARTIAL = 'partial';

    public const STATUS_ABSENT = 'absent';

    public const FINAL_STATUSES = [self::STATUS_PRESENT, self::STATUS_PARTIAL, self::STATUS_ABSENT];

    public const LABELS = [
        self::STATUS_PENDING => 'Chờ tới giờ',
        self::STATUS_LATE => 'Đang trễ',
        self::STATUS_ABSENT_PENDING => 'Chưa vào học',
        self::STATUS_IN_PROGRESS => 'Đang học',
        self::STATUS_PRESENT => 'Có mặt',
        self::STATUS_PARTIAL => 'Học chưa đủ',
        self::STATUS_ABSENT => 'Vắng',
    ];

    /** Màu badge Bootstrap — xanh/vàng/đỏ để phụ huynh nhìn là hiểu. */
    public const COLORS = [
        self::STATUS_PENDING => 'light border',
        self::STATUS_LATE => 'warning',
        self::STATUS_ABSENT_PENDING => 'warning',
        self::STATUS_IN_PROGRESS => 'primary',
        self::STATUS_PRESENT => 'success',
        self::STATUS_PARTIAL => 'warning',
        self::STATUS_ABSENT => 'danger',
    ];

    protected $fillable = [
        'student_id', 'attendance_date', 'scheduled_start', 'scheduled_end', 'scheduled_minutes', 'status',
        'baseline_active_seconds', 'active_seconds', 'entered_at', 'idle_flagged_at', 'late_minutes', 'quiz_submitted',
        'finalized_at', 'not_started_notified_at', 'absent_notified_at',
    ];

    protected function casts(): array
    {
        return [
            'attendance_date' => 'date',
            'scheduled_start' => 'datetime',
            'scheduled_end' => 'datetime',
            'scheduled_minutes' => 'integer',
            'baseline_active_seconds' => 'integer',
            'active_seconds' => 'integer',
            'late_minutes' => 'integer',
            'entered_at' => 'datetime',
            'idle_flagged_at' => 'datetime',
            'quiz_submitted' => 'boolean',
            'finalized_at' => 'datetime',
            'not_started_notified_at' => 'datetime',
            'absent_notified_at' => 'datetime',
        ];
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL_STATUSES, true);
    }

    public function label(): string
    {
        return self::LABELS[$this->status] ?? $this->status;
    }

    public function color(): string
    {
        return self::COLORS[$this->status] ?? 'light border';
    }

    public function activeMinutes(): int
    {
        return intdiv($this->active_seconds, 60);
    }
}
