<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use Illuminate\Http\Request;
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
}
