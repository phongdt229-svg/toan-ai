<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\SeoPageRequest;
use App\Models\SeoPage;
use App\Services\Content\SeoPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Sửa title/meta_description/meta_keywords các trang tĩnh công khai — danh sách trang cố định ở config('seo_pages'). */
class SeoPageController extends Controller
{
    public function __construct(private readonly SeoPageService $seoPages) {}

    public function index(): View
    {
        $saved = SeoPage::query()->get()->keyBy('route_name');

        $pages = collect(config('seo_pages'))->map(function (string $label, string $routeName) use ($saved) {
            $page = $saved->get($routeName);

            return [
                'route_name' => $routeName,
                'label' => $label,
                'title' => $page?->title ?? '',
                'meta_description' => $page?->meta_description ?? '',
                'meta_keywords' => $page?->meta_keywords ?? '',
            ];
        })->values();

        return view('admin.seo.index', ['pages' => $pages]);
    }

    public function update(SeoPageRequest $request): RedirectResponse
    {
        $this->seoPages->save(
            $request->validated('route_name'),
            $request->validated('title'),
            $request->validated('meta_description'),
            $request->validated('meta_keywords'),
        );

        return back()->with('status', 'Đã lưu.');
    }
}
