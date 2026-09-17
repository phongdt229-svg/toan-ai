<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Payment extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Đang chờ',
        self::STATUS_PAID => 'Thành công',
        self::STATUS_FAILED => 'Thất bại',
        self::STATUS_CANCELLED => 'Đã huỷ',
    ];

    protected $fillable = [
        'order_code', 'user_id', 'package_id', 'subscription_id', 'amount', 'currency', 'method', 'status',
        'gateway_request_id', 'gateway_transaction_id', 'gateway_result_code', 'gateway_message',
        'pay_url', 'gateway_response', 'flag_reason', 'paid_at', 'expires_at', 'client_ip',
    ];

    protected $hidden = ['gateway_response'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_response' => 'array',
            'gateway_result_code' => 'integer',
            'paid_at' => 'datetime',
            'expires_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'order_code';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function webhookLogs(): HasMany
    {
        return $this->hasMany(PaymentWebhookLog::class, 'order_code', 'order_code');
    }

    /** Chỉ người trả tiền xem được đơn (id trên URL là mã đơn, nhưng vẫn kiểm tra chủ). */
    public function isOwnedBy(?User $user): bool
    {
        return $user !== null && (int) $this->user_id === (int) $user->id;
    }

    public function isPending(): bool
    {
        return $this->status === self::STATUS_PENDING;
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID;
    }

    /** Số tiền nguyên VND — so khớp với IPN bằng số nguyên, tránh lệch kiểu "99000.00" vs 99000. */
    public function amountInt(): int
    {
        return (int) round((float) $this->amount);
    }

    public function statusLabel(): string
    {
        return self::STATUS_LABELS[$this->status] ?? $this->status;
    }

    public function amountLabel(): string
    {
        return number_format((float) $this->amount, 0, ',', '.').'₫';
    }
}
