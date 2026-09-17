<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AnalyticsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly AnalyticsService $analytics) {}

    public function index(): View
    {
        return view('admin.dashboard', ['a' => $this->analytics->overview()]);
    }

    /** Số liệu cache 10 phút — admin muốn xem ngay sau một thay đổi thì bấm làm mới. */
    public function refresh(): RedirectResponse
    {
        $this->analytics->forget();

        return redirect()->route('admin.dashboard')->with('status', 'Đã cập nhật số liệu.');
    }
}
