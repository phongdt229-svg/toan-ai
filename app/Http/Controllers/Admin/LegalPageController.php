<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LegalPageActionRequest;
use App\Http\Requests\Admin\LegalPageRequest;
use App\Models\LegalPage;
use App\Services\Content\LegalPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Quản trị → Chính sách: tạo / sửa trang chính sách. Slug cố định, xem LegalPageService. */
class LegalPageController extends Controller
{
    public function __construct(private readonly LegalPageService $pages) {}

    public function index(): View
    {
        return view('admin.legal.index', [
            'pages' => LegalPage::ordered()->get(),
            'service' => $this->pages,
        ]);
    }

    public function create(): View
    {
        return view('admin.legal.form', [
            'page' => new LegalPage(['is_active' => true, 'sort_order' => (int) LegalPage::max('sort_order') + 1]),
            'content' => '',
            'usesDefault' => false,
            'services' => [],
        ]);
    }

    public function store(LegalPageRequest $request): RedirectResponse
    {
        $page = $this->pages->create($request->pageData());

        return redirect()->route('admin.legal.edit', $page)
            ->with('status', "Đã tạo trang «{$page->title}» tại /{$page->slug}.");
    }

    public function edit(LegalPage $page): View
    {
        return view('admin.legal.form', [
            'page' => $page,
            // Chưa soạn bao giờ: nạp sẵn bản mặc định đang hiện trên web để admin sửa tiếp, không phải gõ lại.
            'content' => $this->pages->editableContent($page),
            'usesDefault' => $this->pages->usesDefault($page),
            'services' => $page->slug === 'chinh-sach-bao-mat' ? $this->pages->activeServices() : [],
        ]);
    }

    public function update(LegalPageRequest $request, LegalPage $page): RedirectResponse
    {
        $this->pages->update($page, $request->pageData());

        return redirect()->route('admin.legal.edit', $page)->with('status', 'Đã lưu. Trang công khai hiện nội dung mới.');
    }

    public function reset(LegalPageActionRequest $request, LegalPage $page): RedirectResponse
    {
        $this->pages->resetToDefault($page);

        return redirect()->route('admin.legal.edit', $page)->with('status', 'Đã khôi phục nội dung mặc định.');
    }

    public function destroy(LegalPageActionRequest $request, LegalPage $page): RedirectResponse
    {
        $this->pages->delete($page);

        return redirect()->route('admin.legal.index')->with('status', "Đã xoá trang «{$page->title}».");
    }
}
