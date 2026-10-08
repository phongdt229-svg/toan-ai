<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Grade extends Model
{
    protected $fillable = ['name', 'level', 'slug', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return [
            'level' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    public function studentProfiles(): HasMany
    {
        return $this->hasMany(StudentProfile::class);
    }

    public function subjects(): HasMany
    {
        return $this->hasMany(Subject::class);
    }

    /** Lớp đang mở VÀ nằm trong phạm vi hệ thống nhận học sinh (D-02, config/learning.php). */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)
            ->whereBetween('level', [config('learning.grade_min'), config('learning.grade_max')]);
    }

    /** "1 → 12" / "6 → 12" — dùng cho chữ trên trang công khai, để không quảng cáo lớp không nhận. */
    public static function rangeLabel(string $separator = ' → '): string
    {
        return config('learning.grade_min').$separator.config('learning.grade_max');
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('level');
    }
}
