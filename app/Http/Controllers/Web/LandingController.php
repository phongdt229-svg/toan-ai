<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\BlogPost;
use App\Models\Grade;
use App\Models\Package;
use Illuminate\View\View;

class LandingController extends Controller
{
    public function index(): View
    {
        return view('public.landing', [
            'grades' => Grade::active()->ordered()->get(),
            // Giá luôn lấy từ DB (§18), nhóm theo hạng Free/Pro/Premium.
            'packages' => Package::catalog(),
            'latestPosts' => BlogPost::published()->with('category:id,name')->latest('published_at')->latest('id')->limit(6)->get(),
        ]);
    }
}
