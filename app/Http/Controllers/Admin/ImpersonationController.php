<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Admin\ImpersonationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class ImpersonationController extends Controller
{
    public function __construct(private readonly ImpersonationService $impersonation) {}

    public function start(Request $request, User $user): RedirectResponse
    {
        try {
            $this->impersonation->start($request, $request->user(), $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect($user->homeRoute())->with('status', "Đang đăng nhập hộ {$user->name}.");
    }

    /** Ngoài nhóm quản trị: lúc này người đăng nhập là học sinh/giáo viên, không còn role admin. */
    public function stop(Request $request): RedirectResponse
    {
        $target = $this->impersonation->stop($request);

        return $target
            ? redirect()->route('admin.users.show', $target)->with('status', 'Đã thoát chế độ đăng nhập hộ.')
            : redirect()->route('login');
    }
}
