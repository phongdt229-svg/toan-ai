<?php

namespace App\Services\Content;

use App\Models\SeoPage;
use Illuminate\Support\Facades\Cache;

/**
 * Override meta_description theo route, admin sửa ở Quản trị → SEO thay vì đụng code.
 * Đọc ở layouts/base.blade.php mỗi lượt render nên cache 10 phút như AnalyticsService.
 */
class SeoPageService
{
    public const CACHE_KEY = 'seo_pages.overrides.v1';

    public const CACHE_SECONDS = 600;

    /** @return array<string, string> route_name => meta_description (chỉ dòng có nội dung) */
    public function overrides(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => SeoPage::query()
            ->whereNotNull('meta_description')
            ->where('meta_description', '!=', '')
            ->pluck('meta_description', 'route_name')
            ->all());
    }

    public function descriptionFor(?string $routeName): ?string
    {
        return $routeName ? ($this->overrides()[$routeName] ?? null) : null;
    }

    /** null/rỗng = xoá override, quay lại mô tả gốc viết trong Blade. */
    public function save(string $routeName, ?string $description): void
    {
        SeoPage::updateOrCreate(['route_name' => $routeName], ['meta_description' => $description]);
        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
