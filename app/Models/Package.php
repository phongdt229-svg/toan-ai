<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Package extends Model
{
    public const TIER_FREE = 'free';

    public const TIER_PRO = 'pro';

    public const TIER_PREMIUM = 'premium';

    /** Thứ bậc gói — số lớn bao trùm số nhỏ. */
    public const TIER_RANK = [self::TIER_FREE => 0, self::TIER_PRO => 1, self::TIER_PREMIUM => 2];

    public const TIER_LABELS = [self::TIER_FREE => 'Free', self::TIER_PRO => 'Pro', self::TIER_PREMIUM => 'Premium'];

    protected $fillable = [
        'name', 'slug', 'tier', 'price', 'currency', 'duration_days', 'description',
        'is_default', 'is_active', 'is_highlighted', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'duration_days' => 'integer',
            'is_default' => 'boolean',
            'is_active' => 'boolean',
            'is_highlighted' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function features(): HasMany
    {
        return $this->hasMany(PackageFeature::class)->orderBy('sort_order');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('price');
    }

    public function isFree(): bool
    {
        return (float) $this->price <= 0;
    }

    public function tierLabel(): string
    {
        return self::TIER_LABELS[$this->tier] ?? $this->tier;
    }

    /** Giá hiển thị kiểu Việt: 99.000₫ */
    public function priceLabel(): string
    {
        return $this->isFree() ? 'Miễn phí' : number_format((float) $this->price, 0, ',', '.').'₫';
    }

    public function durationLabel(): ?string
    {
        return match (true) {
            $this->duration_days === null => null,
            $this->duration_days % 365 === 0 => ($this->duration_days / 365).' năm',
            $this->duration_days % 30 === 0 => ($this->duration_days / 30).' tháng',
            default => $this->duration_days.' ngày',
        };
    }

    /**
     * Gói đang bán, nhóm theo tier. Tính năng hiển thị lấy từ gói đầu tiên của tier (các kỳ hạn
     * cùng tier có cùng quyền lợi, chỉ khác thời hạn và giá).
     *
     * @return Collection<string, array{tier: string, label: string, packages: Collection<int, self>, features: Collection, highlighted: bool}>
     */
    public static function catalog(): Collection
    {
        return self::active()->ordered()->with('features')->get()
            ->groupBy('tier')
            ->sortBy(fn ($group, $tier) => self::TIER_RANK[$tier] ?? 99)
            ->map(fn (Collection $packages, string $tier) => [
                'tier' => $tier,
                'label' => self::TIER_LABELS[$tier] ?? $tier,
                'packages' => $packages->values(),
                'features' => $packages->first()->features->where('show_on_pricing', true)->values(),
                'highlighted' => $packages->contains('is_highlighted', true),
            ]);
    }
}
