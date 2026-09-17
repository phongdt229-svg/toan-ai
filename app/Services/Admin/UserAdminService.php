<?php

namespace App\Services\Admin;

use App\Models\Role;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/** Khoá / mở khoá tài khoản (§29). Mọi thao tác ghi audit log. */
class UserAdminService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function suspend(User $admin, User $user, string $reason): void
    {
        if ($admin->is($user)) {
            throw new RuntimeException('Không thể tự khoá tài khoản của mình.');
        }

        if ($user->status === User::STATUS_SUSPENDED) {
            throw new RuntimeException('Tài khoản này đã bị khoá.');
        }

        if ($user->isAdmin() && $this->activeAdminCount() <= 1) {
            throw new RuntimeException('Phải còn ít nhất một quản trị viên đang hoạt động.');
        }

        DB::transaction(function () use ($user, $reason) {
            $old = $user->status;
            $user->update(['status' => User::STATUS_SUSPENDED]);

            // Đá khỏi mọi phiên: token API thu hồi ngay; phiên web bị middleware `active` đăng xuất ở request kế tiếp,
            // xoá luôn session trong DB (nếu dùng driver database) cho chắc.
            $user->tokens()->delete();
            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            $this->audit->log('user.suspended', $user, ['status' => $old], ['status' => User::STATUS_SUSPENDED, 'reason' => $reason]);
        });
    }

    public function reactivate(User $user): void
    {
        if ($user->status !== User::STATUS_SUSPENDED) {
            throw new RuntimeException('Chỉ mở khoá được tài khoản đang bị khoá.');
        }

        $user->update(['status' => User::STATUS_ACTIVE]);
        $this->audit->log('user.reactivated', $user, ['status' => User::STATUS_SUSPENDED], ['status' => User::STATUS_ACTIVE]);
    }

    private function activeAdminCount(): int
    {
        return User::where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN))
            ->count();
    }
}
