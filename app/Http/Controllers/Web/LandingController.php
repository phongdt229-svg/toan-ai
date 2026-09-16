<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('public.landing', [
            'grades' => Grade::active()->ordered()->get(),
            // Phase 8 sẽ thay bằng Package::active()->get() — giá luôn lấy từ DB (§18).
            'packages' => collect(),
        ]);
    }
}
