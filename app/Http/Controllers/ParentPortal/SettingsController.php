<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\ParentPortal\UpdateParentSettingsRequest;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AccountService;
use App\Services\Parenting\ChildLinkService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(
        private readonly ChildLinkService $links,
        private readonly AccountService $account,
    ) {}

    public function edit(Request $request): View
    {
        return view('parent.settings', [
            'profile' => $this->links->ensureProfile($request->user()),
        ]);
    }

    public function update(UpdateParentSettingsRequest $request): RedirectResponse
    {
        // Công tắc bỏ chọn thì không gửi lên → boolean() trả false, đúng ý "tắt".
        $this->links->ensureProfile($request->user())->update([
            'weekly_report_enabled' => $request->boolean('weekly_report_enabled'),
            'session_events_enabled' => $request->boolean('session_events_enabled'),
            'daily_report_enabled' => $request->boolean('daily_report_enabled'),
        ]);

        return back()->with('status', 'Đã lưu cài đặt.');
    }

    public function updateProfile(UpdateProfileRequest $request): RedirectResponse
    {
        $this->account->updateProfile($request->user(), $request->validated());

        return back()->with('status', 'Đã lưu hồ sơ.');
    }

    public function updatePassword(UpdatePasswordRequest $request): RedirectResponse
    {
        $this->account->updatePassword($request->user(), $request->validated('password'));

        return back()->with('status', 'Đã đổi mật khẩu.');
    }
}
