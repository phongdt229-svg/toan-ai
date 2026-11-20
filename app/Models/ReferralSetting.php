<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** Một dòng duy nhất — cài đặt chương trình giới thiệu (Quản trị → Giới thiệu bạn bè). */
class ReferralSetting extends Model
{
    protected $fillable = ['is_enabled', 'new_user_percent', 'referrer_percent', 'voucher_valid_days', 'max_rewards_per_referrer'];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'new_user_percent' => 'integer',
            'referrer_percent' => 'integer',
            'voucher_valid_days' => 'integer',
            'max_rewards_per_referrer' => 'integer',
        ];
    }

    /**
     * Dòng cài đặt; DB chưa có thì coi như tắt. Không nhớ tạm: queue worker chạy nhiều ngày
     * phải thấy ngay khi quản trị tắt chương trình.
     */
    public static function current(): self
    {
        return self::query()->first() ?? new self([
            'is_enabled' => false, 'new_user_percent' => 0, 'referrer_percent' => 0,
            'voucher_valid_days' => 60, 'max_rewards_per_referrer' => 0,
        ]);
    }
}
