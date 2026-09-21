<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Trung tâm hướng dẫn (/huong-dan). Nội dung nằm ở config/guides.php — không có bảng DB,
 * nên trang này chạy được cả khi chưa đăng nhập và không tốn truy vấn.
 */
class GuideController extends Controller
{
    public function index(Request $request): View
    {
        $search = trim($request->string('q')->toString());
        $audience = $request->string('doi-tuong')->toString();
        $articles = $this->articles();

        if ($search !== '') {
            $articles = $articles->filter(fn (array $a) => $this->matches($a, $search));
        }

        if (array_key_exists($audience, config('guides.audiences'))) {
            $articles = $articles->where('audience', $audience);
        }

        return view('public.guides.index', [
            'audiences' => config('guides.audiences'),
            'grouped' => $articles->groupBy('audience'),
            'total' => $articles->count(),
            'search' => $search,
            'audience' => $audience,
        ]);
    }

    public function show(string $slug): View
    {
        $article = $this->articles()->get($slug);

        abort_unless($article, 404);

        return view('public.guides.show', [
            'article' => $article,
            'audiences' => config('guides.audiences'),
            // Bài cùng nhóm đối tượng để đọc tiếp, bỏ chính bài đang xem.
            'related' => $this->articles()
                ->where('audience', $article['audience'])
                ->reject(fn (array $a) => $a['slug'] === $slug)
                ->take(4),
        ]);
    }

    /** @return Collection<string, array<string, mixed>> */
    private function articles(): Collection
    {
        return collect(config('guides.articles'))
            ->map(fn (array $article, string $slug) => [...$article, 'slug' => $slug]);
    }

    /** Tìm trong tiêu đề, tóm tắt và nội dung từng bước; bỏ dấu để gõ "huong dan" cũng ra. */
    private function matches(array $article, string $keyword): bool
    {
        $haystack = collect([$article['title'], $article['summary']])
            ->merge(collect($article['steps'] ?? [])->flatten())
            ->merge($article['tips'] ?? [])
            ->implode(' ');

        return Str::contains(
            Str::lower(Str::ascii($haystack)),
            Str::lower(Str::ascii($keyword)),
        );
    }
}
