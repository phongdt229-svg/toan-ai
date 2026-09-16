<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $children = $request->user()
            ->children()
            ->wherePivot('status', 'linked')
            ->with('studentProfile.grade')
            ->get();

        // Phase 6 sẽ nạp báo cáo thật cho từng con (§14).
        return view('parent.dashboard', ['children' => $children]);
    }
}
