<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use Illuminate\View\View;

/** Trang công khai "Tin tức" — bài giới thiệu + khuyến mãi. Chỉ hiện bài đã xuất bản. */
class BlogController extends Controller
{
    public function index(Request $request): View
    {
        $categorySlug = $request->string('danh-muc')->toString();
        $category = $categorySlug !== '' ? BlogCategory::where('slug', $categorySlug)->first() : null;

        return view('public.blog.index', [
            'posts' => BlogPost::query()
                ->published()
                ->with('category:id,name,slug')
                ->when($category, fn ($q) => $q->where('blog_category_id', $category->id))
                ->latest('published_at')->latest('id')
                ->paginate(9)
                ->withQueryString(),
            'categories' => BlogCategory::ordered()->withCount(['posts' => fn ($q) => $q->published()])->get(),
            'activeCategory' => $category,
        ]);
    }

    public function show(BlogPost $post): View
    {
        abort_unless($post->isPublished(), 404);

        return view('public.blog.show', [
            'post' => $post->load('category:id,name,slug', 'author:id,name'),
            'related' => BlogPost::published()
                ->where('blog_category_id', $post->blog_category_id)
                ->where('id', '!=', $post->id)
                ->latest('published_at')->latest('id')
                ->limit(3)
                ->get(),
        ]);
    }

    /**
     * RSS 2.0 cho Tin tức — 20 bài mới nhất. Sinh trực tiếp như sitemap.xml, không cache file
     * (số bài còn nhỏ). `description` dùng tóm tắt thuần chữ (không phải {!! !!}), nên không cần
     * qua HtmlSanitizer riêng — nội dung đầy đủ đã sạch sẵn nhưng feed chỉ đưa tóm tắt.
     */
    public function feed(): Response
    {
        $posts = BlogPost::published()->latest('published_at')->latest('id')->limit(20)->get();

        $items = '';
        foreach ($posts as $post) {
            $link = route('blog.show', $post->slug);
            $description = $post->excerpt ?: Str::limit(strip_tags($post->content), 200);

            $items .= '  <item>'."\n"
                .'    <title>'.e($post->title).'</title>'."\n"
                .'    <link>'.e($link).'</link>'."\n"
                .'    <guid isPermaLink="true">'.e($link).'</guid>'."\n"
                .'    <pubDate>'.$post->published_at->toRfc2822String().'</pubDate>'."\n"
                .'    <description>'.e($description).'</description>'."\n"
                .'  </item>'."\n";
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<rss version="2.0">'."\n"
            .'<channel>'."\n"
            .'  <title>Tin tức — TOÁN AI</title>'."\n"
            .'  <link>'.e(route('blog.index')).'</link>'."\n"
            .'  <description>Bài giới thiệu, khuyến mãi và sự kiện mới nhất từ TOÁN AI.</description>'."\n"
            .'  <language>vi</language>'."\n"
            .'  <lastBuildDate>'.now()->toRfc2822String().'</lastBuildDate>'."\n"
            .$items
            .'</channel>'."\n"
            .'</rss>'."\n";

        return response($xml, 200, ['Content-Type' => 'application/rss+xml; charset=UTF-8']);
    }
}
