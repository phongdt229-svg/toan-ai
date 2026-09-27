<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SeoPageRequest;
use App\Models\SeoPage;
use App\Services\Content\SeoPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Sửa meta_description các trang tĩnh công khai — danh sách trang cố định ở config('seo_pages'). */
class SeoPageController extends Controller
{
    public function __construct(private readonly SeoPageService $seoPages) {}

    public function index(): View
    {
        $saved = SeoPage::query()->pluck('meta_description', 'route_name');

        $pages = collect(config('seo_pages'))->map(fn (string $label, string $routeName) => [
            'route_name' => $routeName,
            'label' => $label,
            'meta_description' => $saved[$routeName] ?? '',
        ])->values();

        return view('admin.seo.index', ['pages' => $pages]);
    }

    public function update(SeoPageRequest $request): RedirectResponse
    {
        $this->seoPages->save($request->validated('route_name'), $request->validated('meta_description'));

        return back()->with('status', 'Đã lưu mô tả.');
    }
}
