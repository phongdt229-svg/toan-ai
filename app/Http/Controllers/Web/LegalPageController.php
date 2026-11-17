<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Content\LegalPageService;
use Illuminate\View\View;

/**
 * Trang chính sách công khai. Điều khoản + Bảo mật có route tên riêng (đăng ký, cookie, footer trỏ tới);
 * trang admin tạo thêm đi qua route fallback để giữ đúng đường dẫn gốc /{slug} mà không đè route nào.
 */
class LegalPageController extends Controller
{
    public function __construct(private readonly LegalPageService $pages) {}

    public function terms(): View
    {
        return $this->render('dieu-khoan-su-dung');
    }

    public function privacy(): View
    {
        return $this->render('chinh-sach-bao-mat');
    }

    /** Route fallback: chỉ trả trang chính sách đang bật, mọi đường dẫn lạ khác vẫn 404 như trước. */
    public function fallback(string $path): View
    {
        abort_if(str_contains($path, '/') || ! request()->isMethod('GET'), 404);

        return $this->render($path);
    }

    private function render(string $slug): View
    {
        $page = $this->pages->findActive($slug);

        abort_unless($page, 404);

        return view('public.legal.show', [
            'page' => $page,
            'usesDefault' => $this->pages->usesDefault($page),
            'defaultView' => $this->pages->hasDefault($page) ? $this->pages->defaultView($page) : null,
            'updatedLabel' => $this->pages->updatedLabel($page),
            'others' => $this->pages->footerPages()->reject(fn ($p) => $p->id === $page->id),
        ]);
    }
}
