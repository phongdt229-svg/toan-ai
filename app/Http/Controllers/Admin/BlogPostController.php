<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogPostRequest;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Services\Content\BlogReportService;
use App\Services\Content\BlogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Quản lý bài viết Blog / Tin tức — chỉ admin (kế hoạch 26/09). */
class BlogPostController extends Controller
{
    public function __construct(
        private readonly BlogService $blog,
        private readonly BlogReportService $report,
    ) {}

    public function index(Request $request): View
    {
        return view('admin.blog.index', [
            'summary' => $this->report->summary(),
            'daily' => $this->report->daily(),
            'byCategory' => $this->report->byCategory(),
            'posts' => BlogPost::query()
                ->with('category:id,name', 'author:id,name')
                ->latest('id')
                ->paginate(config('site.per_page'))
                ->withQueryString(),
        ]);
    }

    public function create(): View
    {
        return view('admin.blog.form', [
            'post' => new BlogPost(['status' => BlogPost::STATUS_DRAFT]),
            'categories' => BlogCategory::ordered()->get(),
        ]);
    }

    public function store(BlogPostRequest $request): RedirectResponse
    {
        $post = $this->blog->create($request->safe()->except('cover'), $request->user(), $request->file('cover'));

        return redirect()->route('admin.blog.edit', $post)->with('status', "Đã tạo bài «{$post->title}».");
    }

    public function edit(BlogPost $post): View
    {
        return view('admin.blog.form', [
            'post' => $post,
            'categories' => BlogCategory::ordered()->get(),
        ]);
    }

    public function update(BlogPostRequest $request, BlogPost $post): RedirectResponse
    {
        $this->blog->update($post, $request->safe()->except('cover'), $request->file('cover'));

        return redirect()->route('admin.blog.edit', $post)->with('status', 'Đã lưu bài viết.');
    }

    public function destroy(BlogPost $post): RedirectResponse
    {
        $this->blog->destroy($post);

        return redirect()->route('admin.blog.index')->with('status', 'Đã xoá bài viết.');
    }
}
