<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Chặn tài khoản chưa được duyệt (giáo viên `pending`) hoặc bị khoá.
 * Giáo viên pending vẫn đăng nhập được nhưng chỉ thấy trang "chờ duyệt".
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->status === User::STATUS_PENDING) {
            return $request->expectsJson()
                ? response()->json([
                    'success' => false,
                    'message' => 'Tài khoản đang chờ quản trị viên duyệt.',
                ], 403)
                : redirect()->route('account.pending');
        }

        if (in_array($user->status, [User::STATUS_SUSPENDED, User::STATUS_REJECTED], true)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->withErrors(['email' => 'Tài khoản của bạn đã bị khoá hoặc bị từ chối.']);
        }

        return $next($request);
    }
}
