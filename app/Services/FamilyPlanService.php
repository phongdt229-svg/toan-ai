<?php

namespace App\Services;

use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Gói gia đình: chủ gói (học sinh đứng tên đơn) chia sẻ quyền gói cho anh chị em bằng MÃ GIA ĐÌNH.
 *
 * Không tạo subscription riêng cho thành viên — `SubscriptionService::effective()` đọc thêm bảng
 * subscription_members. Mọi thay đổi thành viên phải qua đây (khoá dòng gói để không vượt số chỗ).
 */
class FamilyPlanService
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly AuditLogger $audit,
    ) {}

    /** Gói gia đình đang hiệu lực mà em ĐỨNG TÊN (null nếu không có). */
    public function ownedBy(User $user): ?Subscription
    {
        return Subscription::query()
            ->effective()
            ->where('user_id', $user->id)
            ->whereHas('package', fn ($q) => $q->where('max_members', '>', 1))
            ->with('package', 'members:id,name')
            ->latest('ends_at')
            ->first();
    }

    /** Gói gia đình đang hiệu lực mà em là THÀNH VIÊN (null nếu không có). */
    public function memberOf(User $user): ?Subscription
    {
        return Subscription::query()
            ->effective()
            ->whereHas('members', fn ($q) => $q->where('users.id', $user->id))
            ->with('package', 'user:id,name')
            ->latest('ends_at')
            ->first();
    }

    /** Chỗ còn trống = tổng chỗ − chủ gói − thành viên. */
    public function seatsLeft(Subscription $subscription): int
    {
        $taken = DB::table('subscription_members')->where('subscription_id', $subscription->id)->count();

        return max(0, (int) $subscription->package->max_members - 1 - $taken);
    }

    /** @throws RuntimeException thông báo hiển thị thẳng cho học sinh */
    public function join(User $student, string $code): Subscription
    {
        $code = strtoupper(trim($code));

        if (! $student->isStudent()) {
            throw new RuntimeException('Chỉ tài khoản học sinh mới vào được gói gia đình.');
        }

        $subscription = DB::transaction(function () use ($student, $code) {
            // Khoá dòng gói: hai em cùng nhập mã khi chỉ còn một chỗ thì em sau phải thấy "hết chỗ".
            $subscription = Subscription::with('package')->where('join_code', $code)->lockForUpdate()->first();

            if (! $subscription || ! $subscription->package->isFamily()) {
                throw new RuntimeException('Mã gia đình không đúng. Hỏi lại người mua gói giúp em nhé.');
            }
            // Gói gia hạn nối tiếp (chưa tới ngày bắt đầu) vẫn nhận thành viên — chỉ chặn gói hết hạn / chưa trả tiền.
            if ($subscription->status !== Subscription::STATUS_ACTIVE || ! $subscription->ends_at?->isFuture()) {
                throw new RuntimeException('Gói gia đình này đã hết hạn hoặc chưa kích hoạt.');
            }
            if ($subscription->user_id === $student->id) {
                throw new RuntimeException('Em là người đứng tên gói này rồi.');
            }
            if ($subscription->members()->where('users.id', $student->id)->exists()) {
                throw new RuntimeException('Em đã ở trong gói gia đình này.');
            }
            if ($this->memberOf($student)) {
                throw new RuntimeException('Em đang ở một gói gia đình khác — rời gói đó trước đã.');
            }
            if ($this->seatsLeft($subscription) === 0) {
                throw new RuntimeException('Gói gia đình đã đủ người.');
            }

            // Vào cả gói đang chạy lẫn gói gia hạn nối tiếp của cùng chủ gói (còn chỗ) — hết gói này là sang gói kia.
            $chain = Subscription::with('package')
                ->where('user_id', $subscription->user_id)
                ->where('status', Subscription::STATUS_ACTIVE)
                ->where('ends_at', '>', now())
                ->whereHas('package', fn ($q) => $q->where('max_members', '>', 1))
                ->lockForUpdate()
                ->get();
            foreach ($chain as $linked) {
                if ($linked->is($subscription) || $this->seatsLeft($linked) > 0) {
                    $linked->members()->syncWithoutDetaching([$student->id => ['joined_at' => now()]]);
                }
            }
            $this->audit->log('family_plan.joined', $subscription, null, ['member_id' => $student->id]);

            return $subscription;
        });

        $this->subscriptions->forget($student);

        return $subscription;
    }

    /** Chủ gói, người trả tiền hoặc quản trị gỡ một thành viên; thành viên tự rời gói. */
    public function remove(Subscription $subscription, User $member, User $actor): void
    {
        if (! $this->canManage($actor, $subscription) && $actor->id !== $member->id) {
            throw new RuntimeException('Bạn không có quyền gỡ thành viên khỏi gói này.');
        }

        // Gia hạn chuyển thành viên sang đăng ký nối tiếp — gỡ thì gỡ khỏi MỌI đăng ký còn hiệu lực của chủ gói,
        // không thì em vẫn còn quyền nhờ đăng ký cũ tới hết hạn.
        $ids = Subscription::where('user_id', $subscription->user_id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->pluck('id');
        $removed = DB::table('subscription_members')
            ->whereIn('subscription_id', $ids->push($subscription->id)->unique())
            ->where('user_id', $member->id)
            ->delete();

        if ($removed > 0) {
            $this->audit->log('family_plan.removed', $subscription, ['member_id' => $member->id], ['by' => $actor->id]);
            $this->subscriptions->forget($member);
        }
    }

    public function canManage(User $actor, Subscription $subscription): bool
    {
        return $actor->isAdmin()
            || $actor->id === $subscription->user_id
            || $actor->id === $subscription->purchased_by;
    }
}
