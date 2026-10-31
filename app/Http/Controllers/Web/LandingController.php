<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

class LandingController extends Controller
{
    /** Trang chủ theo mẫu V6 là nội dung tĩnh — không cần dữ liệu từ DB. */
    public function index(): View
    {
        return view('public.landing');
    }
}
