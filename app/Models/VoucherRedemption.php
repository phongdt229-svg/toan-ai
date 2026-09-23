<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Một lượt dùng mã, gắn với đúng một đơn.
 *
 * Vòng đời: tạo lúc tạo đơn (giữ chỗ) → `redeemed_at` khi trả tiền xong
 * → hoặc `released_at` khi đơn hỏng/hết hạn, lượt trả về kho.
 */
class VoucherRedemption extends Model
{
    protected $fillable = ['voucher_id', 'user_id', 'payment_id', 'discount_amount', 'redeemed_at', 'released_at'];

    protected function casts(): array
    {
        return [
            'discount_amount' => 'decimal:2',
            'redeemed_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }

    public function voucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
