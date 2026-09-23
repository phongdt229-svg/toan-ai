<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * Mã giảm giá (§8b). Model chỉ mô tả dữ liệu và trả lời các câu hỏi đơn lẻ —
 * việc quyết định "mã này có dùng được không" nằm ở `VoucherService`, vì nó cần khoá dòng.
 */
class Voucher extends Model
{
    public const TYPE_PERCENT = 'percent';
    public const TYPE_FIXED = 'fixed';

    public const TYPE_LABELS = [
        self::TYPE_PERCENT => 'Giảm theo %',
        self::TYPE_FIXED => 'Giảm số tiền',
    ];

    protected $fillable = [
        'code', 'description', 'type', 'value', 'max_discount', 'min_order_amount',
        'starts_at', 'ends_at', 'max_uses', 'max_uses_per_user', 'is_active', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'max_discount' => 'decimal:2',
            'min_order_amount' => 'decimal:2',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'max_uses' => 'integer',
            'max_uses_per_user' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Mã luôn nằm trong DB ở dạng in hoa — tra cứu chỉ cần upper chuỗi người dùng gõ.
        static::saving(fn (Voucher $voucher) => $voucher->code = self::normalizeCode($voucher->code));
    }

    public static function normalizeCode(?string $code): string
    {
        return Str::upper(trim((string) $code));
    }

    /** Trống = áp dụng mọi gói. */
    public function packages(): BelongsToMany
    {
        return $this->belongsToMany(Package::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(VoucherRedemption::class);
    }

    /** Lượt đang giữ chỗ hoặc đã dùng thật — lượt của đơn hỏng đã được trả lại nên không tính. */
    public function heldRedemptions(): HasMany
    {
        return $this->redemptions()->whereNull('released_at');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function hasStarted(): bool
    {
        return $this->starts_at === null || $this->starts_at->isPast();
    }

    public function hasEnded(): bool
    {
        return $this->ends_at !== null && $this->ends_at->isPast();
    }

    public function appliesTo(Package $package): bool
    {
        $only = $this->relationLoaded('packages') ? $this->packages : $this->packages()->get();

        return $only->isEmpty() || $only->contains('id', $package->id);
    }

    /**
     * Số tiền được giảm cho một mức giá — chỉ là phép tính, không kiểm điều kiện.
     * Không bao giờ giảm quá giá gói (tránh số âm).
     */
    public function discountOn(float $price): float
    {
        $discount = $this->type === self::TYPE_PERCENT
            ? $price * ((float) $this->value / 100)
            : (float) $this->value;

        if ($this->max_discount !== null) {
            $discount = min($discount, (float) $this->max_discount);
        }

        // Làm tròn xuống về đồng: MoMo chỉ nhận số nguyên VND.
        return (float) min(floor($discount), $price);
    }

    public function valueLabel(): string
    {
        return $this->type === self::TYPE_PERCENT
            ? rtrim(rtrim(number_format((float) $this->value, 2, ',', '.'), '0'), ',').'%'
            : number_format((float) $this->value, 0, ',', '.').'₫';
    }
}
