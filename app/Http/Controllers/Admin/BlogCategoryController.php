<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\BlogCategoryRequest;
use App\Models\BlogCategory;
use App\Services\Content\BlogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use RuntimeException;

/** Danh mục bài viết — quản lý đơn giản trên một trang (đúng kiểu CurriculumController). */
class BlogCategoryController extends Controller
{
    public function __construct(private readonly BlogService $blog) {}

    public function index(): View
    {
        return view('admin.blog-categories.index', [
            'categories' => BlogCategory::ordered()->withCount('posts')->get(),
        ]);
    }

    public function store(BlogCategoryRequest $request): RedirectResponse
    {
        $this->blog->createCategory($request->validated('name'));

        return back()->with('status', 'Đã thêm danh mục.');
    }

    public function update(BlogCategoryRequest $request, BlogCategory $category): RedirectResponse
    {
        $this->blog->updateCategory($category, $request->validated('name'));

        return back()->with('status', 'Đã đổi tên danh mục.');
    }

    public function destroy(BlogCategory $category): RedirectResponse
    {
        try {
            $this->blog->destroyCategory($category);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Đã xoá danh mục.');
    }
}
