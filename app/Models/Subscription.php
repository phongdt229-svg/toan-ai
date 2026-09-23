<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subscription extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_ACTIVE = 'active';

    public const STATUS_EXPIRED = 'expired';

    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Chờ thanh toán',
        self::STATUS_ACTIVE => 'Đang dùng',
        self::STATUS_EXPIRED => 'Hết hạn',
        self::STATUS_CANCELLED => 'Đã huỷ',
    ];

    protected $fillable = [
        'user_id', 'purchased_by', 'package_id', 'status', 'price_paid', 'duration_days',
        'starts_at', 'ends_at', 'activated_at', 'cancelled_at', 'cancel_reason', 'source',
        'expiry_reminded_days',
    ];

    protected function casts(): array
    {
        return [
            'price_paid' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'activated_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function purchaser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'purchased_by');
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Đang có hiệu lực NGAY LÚC NÀY. Quyền truy cập dựa vào ends_at, không chờ job hết hạn chạy —
     * job chỉ dọn trạng thái cho đúng.
     */
    public function scopeEffective(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_ACTIVE)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>', now());
    }

    public function isEffective(): bool
    {
        return $this->status === self::STATUS_ACTIVE
            && $this->starts_at?->lte(now())
            && $this->ends_at?->gt(now());
    }

    public function statusLabel(): string
    {
        if ($this->status === self::STATUS_ACTIVE && $this->starts_at?->isFuture()) {
            return 'Chờ tới lượt';
        }

        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function daysLeft(): int
    {
        return $this->ends_at ? max(0, (int) ceil(now()->diffInHours($this->ends_at, false) / 24)) : 0;
    }
}
