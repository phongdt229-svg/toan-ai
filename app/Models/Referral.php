<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Referral extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_REWARDED = 'rewarded';

    public const STATUS_VOID = 'void';

    public const STATUS_LABELS = [
        self::STATUS_PENDING => 'Chưa mua gói',
        self::STATUS_REWARDED => 'Đã thưởng',
        self::STATUS_VOID => 'Huỷ (đơn đã hoàn tiền)',
    ];

    protected $fillable = ['referrer_id', 'referred_id', 'status', 'welcome_voucher_id', 'reward_voucher_id', 'payment_id', 'rewarded_at'];

    protected function casts(): array
    {
        return ['rewarded_at' => 'datetime'];
    }

    public function referrer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referrer_id')->withTrashed();
    }

    public function referred(): BelongsTo
    {
        return $this->belongsTo(User::class, 'referred_id')->withTrashed();
    }

    public function welcomeVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'welcome_voucher_id');
    }

    public function rewardVoucher(): BelongsTo
    {
        return $this->belongsTo(Voucher::class, 'reward_voucher_id');
    }
}
