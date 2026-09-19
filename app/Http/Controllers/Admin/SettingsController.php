<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdatePasswordRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Services\AccountService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly AccountService $account) {}

    public function edit(Request $request): View
    {
        return view('admin.settings', ['user' => $request->user()]);
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
