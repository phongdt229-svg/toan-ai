<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\DeleteAccountRequest;
use App\Services\Auth\AccountDeletionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

class AccountDeletionController extends Controller
{
    public function __construct(private readonly AccountDeletionService $deletion) {}

    public function destroy(DeleteAccountRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $user = $request->user();

        try {
            $this->deletion->request($user, $data['reason'] ?? null);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home')->with('status',
            'Đã nhận yêu cầu xoá tài khoản. Dữ liệu cá nhân sẽ được xoá sau '
            .AccountDeletionService::GRACE_DAYS.' ngày; đổi ý trong thời gian đó thì liên hệ '
            .config('site.email').'.');
    }
}
