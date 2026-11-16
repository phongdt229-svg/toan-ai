<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\LegalPageRequest;
use App\Services\Content\LegalPageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/** Quản trị → Chính sách: soạn Điều khoản sử dụng / Chính sách bảo mật. Chỉ admin (route nhóm admin + Form Request). */
class LegalPageController extends Controller
{
    public function __construct(private readonly LegalPageService $pages) {}

    public function index(): View
    {
        return view('admin.legal.index', [
            'pages' => collect(LegalPageService::PAGES)->map(fn (array $meta, string $slug) => $meta + [
                'slug' => $slug,
                'custom' => $this->pages->custom($slug),
                'updated' => $this->pages->updatedLabel($slug),
            ]),
        ]);
    }

    public function edit(string $page): View
    {
        $custom = $this->pages->custom($page);

        return view('admin.legal.edit', [
            'slug' => $page,
            'meta' => LegalPageService::PAGES[$page],
            'custom' => $custom,
            // Chưa soạn bao giờ: nạp sẵn bản mặc định đang hiện trên web để admin sửa tiếp, không phải gõ lại từ đầu.
            'content' => $custom?->content ?? $this->pages->defaultHtml($page),
            'services' => $page === 'privacy' ? $this->pages->activeServices() : [],
        ]);
    }

    public function update(LegalPageRequest $request, string $page): RedirectResponse
    {
        $saved = $this->pages->update($page, $request->validated('content'));

        return redirect()->route('admin.legal.edit', $page)
            ->with('status', $saved ? 'Đã lưu. Trang công khai hiện nội dung mới.' : 'Nội dung trống — đã dùng lại bản mặc định.');
    }

    public function destroy(string $page): RedirectResponse
    {
        $this->pages->reset($page);

        return redirect()->route('admin.legal.edit', $page)->with('status', 'Đã khôi phục nội dung mặc định.');
    }
}
