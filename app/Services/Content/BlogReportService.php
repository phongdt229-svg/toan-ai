<?php

namespace App\Services\Content;

use App\Models\BlogPost;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Số liệu báo cáo Blog cho trang Quản trị → Bài viết. */
class BlogReportService
{
    private const DAYS = 30;

    /** @return array{total: int, published: int, draft: int} */
    public function summary(): array
    {
        return [
            'total' => BlogPost::count(),
            'published' => BlogPost::where('status', BlogPost::STATUS_PUBLISHED)->count(),
            'draft' => BlogPost::where('status', BlogPost::STATUS_DRAFT)->count(),
        ];
    }

    /**
     * 30 ngày gần đây: số bài xuất bản mỗi ngày. Ngày không có bài vẫn có mặt (0) để trục liền mạch.
     *
     * @return list<array{label: string, count: int}>
     */
    public function daily(): array
    {
        $since = today()->subDays(self::DAYS - 1);

        $rows = BlogPost::query()
            ->where('status', BlogPost::STATUS_PUBLISHED)
            ->whereDate('published_at', '>=', $since)
            ->groupByRaw('DATE(published_at)')
            ->selectRaw('DATE(published_at) d, COUNT(*) c')
            ->get()
            ->keyBy('d');

        return collect(range(0, self::DAYS - 1))->map(function (int $i) use ($since, $rows) {
            $day = $since->copy()->addDays($i)->toDateString();

            return [
                'label' => Carbon::parse($day)->format('d/m'),
                'count' => (int) ($rows->get($day)->c ?? 0),
            ];
        })->all();
    }

    /**
     * Số bài đã xuất bản theo từng danh mục — query thẳng qua join, tránh N+1.
     *
     * @return list<array{label: string, count: int}>
     */
    public function byCategory(): array
    {
        return BlogPost::query()
            ->join('blog_categories', 'blog_categories.id', '=', 'blog_posts.blog_category_id')
            ->where('blog_posts.status', BlogPost::STATUS_PUBLISHED)
            ->groupBy('blog_categories.id', 'blog_categories.name')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(8)
            ->get(['blog_categories.name as label', DB::raw('COUNT(*) as count')])
            ->map(fn ($r) => ['label' => $r->label, 'count' => (int) $r->count])
            ->all();
    }
}
