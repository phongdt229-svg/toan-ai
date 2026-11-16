<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Content\LegalPageService;
use Illuminate\View\View;

/** Điều khoản sử dụng + Chính sách bảo mật — công khai; nội dung admin sửa được, xem LegalPageService. */
class LegalPageController extends Controller
{
    public function __construct(private readonly LegalPageService $pages) {}

    public function terms(): View
    {
        return $this->show('terms');
    }

    public function privacy(): View
    {
        return $this->show('privacy');
    }

    private function show(string $slug): View
    {
        $custom = $this->pages->custom($slug);

        return view("public.legal.{$slug}", [
            'custom' => $custom,
            'updatedLabel' => $this->pages->updatedLabel($slug, $custom),
        ]);
    }
}
