<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Services\Parenting\ChildLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly ChildLinkService $links) {}

    public function edit(Request $request): View
    {
        return view('parent.settings', [
            'profile' => $this->links->ensureProfile($request->user()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $this->links->ensureProfile($request->user())->update([
            'weekly_report_enabled' => $request->boolean('weekly_report_enabled'),
        ]);

        return back()->with('status', 'Đã lưu cài đặt.');
    }
}
