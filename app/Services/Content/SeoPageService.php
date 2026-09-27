<?php

namespace App\Services\Content;

use App\Models\SeoPage;
use Illuminate\Support\Facades\Cache;

/**
 * Override title/meta_description/meta_keywords theo route, admin sửa ở Quản trị → SEO
 * thay vì đụng code. Đọc ở layouts/base.blade.php mỗi lượt render nên cache 10 phút
 * như AnalyticsService.
 */
class SeoPageService
{
    public const CACHE_KEY = 'seo_pages.overrides.v2';

    public const CACHE_SECONDS = 600;

    /** @return array<string, array{title: ?string, meta_description: ?string, meta_keywords: ?string}> route_name => override */
    public function overrides(): array
    {
        return Cache::remember(self::CACHE_KEY, self::CACHE_SECONDS, fn () => SeoPage::query()
            ->get(['route_name', 'title', 'meta_description', 'meta_keywords'])
            ->keyBy('route_name')
            ->map(fn (SeoPage $page) => [
                'title' => $page->title ?: null,
                'meta_description' => $page->meta_description ?: null,
                'meta_keywords' => $page->meta_keywords ?: null,
            ])
            ->all());
    }

    public function titleFor(?string $routeName): ?string
    {
        return $routeName ? $this->overrides()[$routeName]['title'] ?? null : null;
    }

    public function descriptionFor(?string $routeName): ?string
    {
        return $routeName ? $this->overrides()[$routeName]['meta_description'] ?? null : null;
    }

    public function keywordsFor(?string $routeName): ?string
    {
        return $routeName ? $this->overrides()[$routeName]['meta_keywords'] ?? null : null;
    }

    /** Trường nào null/rỗng = xoá override của riêng trường đó, quay lại giá trị gốc viết trong Blade. */
    public function save(string $routeName, ?string $title, ?string $description, ?string $keywords): void
    {
        SeoPage::updateOrCreate(['route_name' => $routeName], [
            'title' => $title,
            'meta_description' => $description,
            'meta_keywords' => $keywords,
        ]);
        $this->forget();
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }
}
