<?php

namespace App\Services\Admin;

use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use RuntimeException;

/**
 * Admin đăng nhập hộ người dùng để tái hiện lỗi ("em không thấy bài"). Cả lúc vào lẫn lúc ra đều
 * ghi audit log; trong lúc mượn, `BlockWhenImpersonating` chặn đổi mật khẩu/email, xoá tài khoản, mua gói.
 */
class ImpersonationService
{
    public const SESSION_KEY = 'impersonator_id';

    public function __construct(private readonly AuditLogger $audit) {}

    public function start(Request $request, User $admin, User $target): void
    {
        if ($request->session()->has(self::SESSION_KEY)) {
            throw new RuntimeException('Đang đăng nhập hộ một tài khoản khác — hãy thoát trước.');
        }

        if ($admin->is($target)) {
            throw new RuntimeException('Không thể đăng nhập hộ chính mình.');
        }

        if ($target->hasRole('admin')) {
            throw new RuntimeException('Không đăng nhập hộ tài khoản quản trị.');
        }

        if ($target->status === User::STATUS_SUSPENDED || $target->trashed()) {
            throw new RuntimeException('Tài khoản đang bị khoá hoặc đã xoá.');
        }

        // Ghi trước khi đổi người: AuditLogger lấy Auth::id() làm người thao tác = admin.
        $this->audit->log('impersonation.started', $target, null, ['target_id' => $target->id]);

        Auth::login($target);
        $request->session()->regenerate();
        $request->session()->put(self::SESSION_KEY, $admin->id);
    }

    public function stop(Request $request): ?User
    {
        $adminId = $request->session()->get(self::SESSION_KEY);
        $admin = $adminId ? User::find($adminId) : null;
        $target = $request->user();

        if (! $admin || ! $admin->hasRole('admin')) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $this->audit->log('impersonation.stopped', $target, null, ['target_id' => $target->id]);

        $request->session()->forget(self::SESSION_KEY);
        Auth::login($admin);
        $request->session()->regenerate();

        return $target;
    }

    public function isImpersonating(Request $request): bool
    {
        return $request->session()->has(self::SESSION_KEY);
    }
}
