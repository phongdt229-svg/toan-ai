<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AccountController extends Controller
{
    /** Trang giáo viên chờ admin duyệt (§5). */
    public function pending(Request $request): View|RedirectResponse
    {
        $user = $request->user();

        if (! $user->isPending()) {
            return redirect()->to($user->homeRoute());
        }

        return view('account.pending', ['user' => $user->load('teacherProfile')]);
    }
}
