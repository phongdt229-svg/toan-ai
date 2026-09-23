<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateAvatarRequest;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

class AvatarController extends Controller
{
    public function __construct(private readonly AvatarService $avatars) {}

    public function store(UpdateAvatarRequest $request): RedirectResponse
    {
        try {
            $this->avatars->store($request->user(), $request->file('avatar'));
        } catch (RuntimeException $e) {
            return back()->withErrors(['avatar' => $e->getMessage()]);
        }

        return back()->with('status', 'Đã cập nhật ảnh đại diện.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        $this->avatars->remove($request->user());

        return back()->with('status', 'Đã xoá ảnh đại diện.');
    }
}
