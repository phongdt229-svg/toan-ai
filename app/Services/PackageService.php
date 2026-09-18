<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageFeature;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Admin sửa gói & giá (§18). Đổi giá không ảnh hưởng đăng ký cũ — subscriptions/payments chụp giá lúc mua.
 */
class PackageService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /** @param  array<string, mixed>  $data  đã validate bởi PackageRequest */
    public function save(?Package $package, array $data): Package
    {
        return DB::transaction(function () use ($package, $data) {
            $isNew = $package === null;
            $package ??= new Package;
            $old = $isNew ? null : $package->only(['name', 'tier', 'price', 'duration_days', 'is_active', 'is_default', 'is_highlighted']);

            $package->fill([
                'name' => $data['name'],
                'tier' => $data['tier'],
                'price' => $data['price'],
                'duration_days' => $data['duration_days'] ?? null,
                'description' => $data['description'] ?? null,
                'sort_order' => $data['sort_order'] ?? 0,
                'is_active' => $data['is_active'],
                'is_highlighted' => $data['is_highlighted'],
                'is_default' => $data['is_default'],
            ]);

            if ($isNew || filled($data['slug'] ?? null)) {
                $package->slug = $data['slug'] ?? null ?: $this->uniqueSlug($data['name']);
            }

            $package->save();

            if ($package->is_default) {
                Package::whereKeyNot($package->id)->where('is_default', true)->update(['is_default' => false]);
            }

            $this->syncFeatures($package, $data);

            $this->audit->log($isNew ? 'package.created' : 'package.updated', $package, $old,
                $package->only(['name', 'tier', 'price', 'duration_days', 'is_active', 'is_default', 'is_highlighted']));

            return $package->load('features');
        });
    }

    /** Gói đã có người mua không xoá được (lịch sử thanh toán trỏ tới) — chỉ tắt bán. */
    public function delete(Package $package): void
    {
        if ($package->is_default) {
            throw new RuntimeException('Không xoá được gói mặc định.');
        }

        if ($package->subscriptions()->exists()) {
            throw new RuntimeException('Gói đã có người mua — hãy tắt bán thay vì xoá.');
        }

        $this->audit->log('package.deleted', $package, $package->only(['name', 'slug', 'price']));
        $package->delete();
    }

    /** @param  array<string, mixed>  $data */
    private function syncFeatures(Package $package, array $data): void
    {
        $sort = 0;
        $keep = [];

        foreach (PackageFeature::KEYS as $key => $description) {
            $input = $data['features'][str_replace('.', '_', $key)] ?? [];
            $isLimit = PackageFeature::KEY_TYPES[$key] === 'limit';
            $limit = $isLimit && ($input['limit'] ?? null) !== null && $input['limit'] !== '' ? (int) $input['limit'] : null;

            $package->features()->updateOrCreate(['key' => $key], [
                'label' => $input['label'] ?? null ?: $description,
                'value' => $isLimit ? '1' : (! empty($input['enabled']) ? '1' : '0'),
                'limit_value' => $limit,
                'show_on_pricing' => ! empty($input['show']),
                'sort_order' => ++$sort,
            ]);
            $keep[] = $key;
        }

        // Dòng mô tả tự do trên bảng giá: mỗi dòng một feature `display.N`.
        $lines = collect(preg_split('/\R/', (string) ($data['display_lines'] ?? '')))
            ->map(fn ($l) => trim($l))->filter()->take(20)->values();

        foreach ($lines as $i => $line) {
            $key = PackageFeature::DISPLAY_PREFIX.($i + 1);
            $package->features()->updateOrCreate(['key' => $key], [
                'label' => Str::limit($line, 188), 'value' => '1', 'limit_value' => null,
                'show_on_pricing' => true, 'sort_order' => ++$sort,
            ]);
            $keep[] = $key;
        }

        $package->features()->whereNotIn('key', $keep)->delete();
    }

    private function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'goi';
        $slug = $base;
        $i = 2;

        while (Package::where('slug', $slug)->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
