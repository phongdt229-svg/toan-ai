<?php

namespace App\Services\Auth;

use App\Models\Role;
use App\Models\User;
use App\Notifications\AccountDeletionRequested;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Xoá tài khoản theo đúng cam kết trong Chính sách bảo mật:
 * yêu cầu xoá → khoá quyền truy cập ngay → sau 30 ngày thì ẩn danh vĩnh viễn.
 *
 * Vì sao ẩn danh chứ không DELETE hẳn: điểm số, bài đã nộp, hoá đơn còn gắn với các bảng khác
 * (lớp học, kế toán). Xoá cứng sẽ làm hỏng lịch sử của giáo viên và sổ sách. Sau khi ẩn danh,
 * bản ghi còn lại không còn dữ liệu cá nhân nào.
 */
class AccountDeletionService
{
    /** Số ngày giữ lại trước khi ẩn danh — khớp với Chính sách bảo mật. */
    public const GRACE_DAYS = 30;

    public function __construct(private readonly AuditLogger $audit) {}

    /** Người dùng tự yêu cầu xoá. Tài khoản bị khoá ngay, còn khôi phục được trong 30 ngày. */
    public function request(User $user, ?string $reason = null): void
    {
        if ($user->isAdmin() && $this->activeAdminCount() <= 1) {
            throw new RuntimeException('Bạn là quản trị viên duy nhất đang hoạt động — hãy chuyển quyền cho người khác trước.');
        }

        DB::transaction(function () use ($user, $reason) {
            $user->tokens()->delete();

            if (config('session.driver') === 'database') {
                DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            }

            $this->audit->log('account.deletion_requested', $user, null, [
                'email' => $user->email,
                'reason' => $reason,
                'purge_after' => now()->addDays(self::GRACE_DAYS)->toDateString(),
            ]);

            // Gửi thư TRƯỚC khi soft delete: sau đó quan hệ notify vẫn chạy nhưng thư báo
            // "đã nhận yêu cầu" nên tới lúc người dùng còn đọc được.
            $user->notify(new AccountDeletionRequested(now()->addDays(self::GRACE_DAYS)));

            $user->delete();
        });
    }

    /** Admin khôi phục trong thời gian chờ. */
    public function restore(User $user, User $admin): void
    {
        if (! $user->trashed()) {
            throw new RuntimeException('Tài khoản này không nằm trong danh sách chờ xoá.');
        }

        $user->restore();
        $this->audit->log('account.restored', $user, null, ['email' => $user->email, 'by' => $admin->id]);
    }

    /**
     * Ẩn danh các tài khoản đã quá hạn giữ. Chạy hằng ngày qua lệnh `accounts:purge`.
     *
     * @return int số tài khoản đã ẩn danh
     */
    public function purgeDue(?int $days = null): int
    {
        $cutoff = now()->subDays($days ?? self::GRACE_DAYS);

        $users = User::onlyTrashed()
            ->where('deleted_at', '<=', $cutoff)
            ->where('email', 'not like', '%@deleted.invalid')
            ->get();

        foreach ($users as $user) {
            $this->anonymise($user);
        }

        return $users->count();
    }

    /** Xoá sạch dữ liệu cá nhân, giữ lại bản ghi vô danh để lịch sử lớp học và sổ sách không vỡ. */
    private function anonymise(User $user): void
    {
        DB::transaction(function () use ($user) {
            // Hồ sơ chứa ngày sinh, địa chỉ, trường học → xoá hẳn.
            $user->studentProfile()->delete();
            $user->teacherProfile()->delete();
            $user->parentProfile()->delete();

            // Nội dung trò chuyện với AI là dữ liệu cá nhân, không cần giữ.
            DB::table('ai_messages')
                ->whereIn('ai_conversation_id', DB::table('ai_conversations')->where('user_id', $user->id)->pluck('id'))
                ->delete();
            DB::table('ai_conversations')->where('user_id', $user->id)->delete();

            // Yêu cầu hỗ trợ giữ lại để đối soát nhưng bỏ tên và email.
            DB::table('support_tickets')->where('user_id', $user->id)->update([
                'name' => 'Người dùng đã xoá',
                'email' => 'deleted@deleted.invalid',
            ]);

            DB::table('notifications')
                ->where('notifiable_type', User::class)
                ->where('notifiable_id', $user->id)
                ->delete();

            $user->forceFill([
                'name' => 'Người dùng đã xoá',
                'email' => "deleted-{$user->id}@deleted.invalid",
                'phone' => null,
                'avatar' => null,
                'password' => Hash::make(Str::random(40)),
                'remember_token' => null,
                'notification_preferences' => null,
            ])->saveQuietly();

            $this->audit->log('account.purged', $user, null, ['user_id' => $user->id]);
        });
    }

    private function activeAdminCount(): int
    {
        return User::where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::ADMIN))
            ->count();
    }
}
