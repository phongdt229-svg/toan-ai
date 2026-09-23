<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PaymentRefund extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Chưa rõ kết quả',
        self::STATUS_SUCCEEDED => 'Đã hoàn',
        self::STATUS_FAILED => 'Thất bại',
    ];

    protected $fillable = [
        'payment_id', 'refund_code', 'amount', 'reason', 'status',
        'gateway_transaction_id', 'gateway_result_code', 'gateway_message', 'requested_by', 'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'gateway_result_code' => 'integer',
            'refunded_at' => 'datetime',
        ];
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }
}
