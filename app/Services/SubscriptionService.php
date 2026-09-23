<?php

namespace App\Services;

use App\Models\Package;
use App\Models\PackageFeature;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use WeakMap;

/**
 * Nguồn duy nhất trả lời "học sinh này đang ở gói nào, được dùng gì" (§18–19).
 *
 * Luồng §19: Package → Payment → Payment thành công → Subscription active → được truy cập.
 * Phase 9 (MoMo) gọi createPending() lúc checkout và activate() khi IPN hợp lệ.
 */
class SubscriptionService
{
    /**
     * Cache theo đúng object User: một trang hỏi tier nhiều lần (bài học, AI, luyện tập) chỉ tốn 1 query.
     * Khoá bằng object (WeakMap) chứ không bằng id, vì controller/service có thể sống qua nhiều request
     * (route cache controller, Octane) — request mới có object User mới nên không đọc nhầm dữ liệu cũ.
     *
     * @var WeakMap<User, array{0: int, 1: Subscription|null}>
     */
    private WeakMap $effectiveCache;

    /** Tăng mỗi khi có ghi (kích hoạt, huỷ…) — mọi instance trong process bỏ cache cũ. */
    private static int $generation = 0;

    public function __construct(private readonly AuditLogger $audit)
    {
        $this->effectiveCache = new WeakMap;
    }

    // --- Đọc ------------------------------------------------------------------------------

    /** Gói đang có hiệu lực, tier cao nhất; null = dùng gói mặc định (Free). */
    public function effective(User $user): ?Subscription
    {
        if ($this->effectiveCache->offsetExists($user) && $this->effectiveCache[$user][0] === self::$generation) {
            return $this->effectiveCache[$user][1];
        }

        $effective = Subscription::query()
            ->effective()
            ->where('user_id', $user->id)
            ->with('package.features')
            ->get()
            ->sortByDesc(fn (Subscription $s) => [Package::TIER_RANK[$s->package->tier] ?? 0, $s->ends_at->timestamp])
            ->first();

        $this->effectiveCache[$user] = [self::$generation, $effective];

        return $effective;
    }

    public function tier(User $user): string
    {
        return $this->effective($user)?->package->tier ?? Package::TIER_FREE;
    }

    /** Gói mặc định cho người chưa mua — null nếu DB chưa seed gói nào. */
    public function defaultPackage(): ?Package
    {
        return once(fn () => Package::with('features')->where('is_default', true)->first());
    }

    /**
     * Tính năng của gói hiện tại. null = gói không khai báo khoá này.
     */
    public function feature(User $user, string $key): ?PackageFeature
    {
        $package = $this->effective($user)?->package ?? $this->defaultPackage();

        return $package?->features->firstWhere('key', $key);
    }

    /**
     * Giới hạn số lượng theo gói. Trả `false` khi hệ thống chưa cấu hình gói nào
     * (để nơi gọi dùng mặc định trong config), null = không giới hạn.
     */
    public function limit(User $user, string $key): int|null|false
    {
        if (! $this->defaultPackage()) {
            return false;
        }

        $feature = $this->feature($user, $key);

        return $feature ? $feature->limit_value : null;
    }

    /** Tính năng bật/tắt. Chưa cấu hình gói nào → cho phép (không khoá cứng một hệ thống mới cài). */
    public function allows(User $user, string $key): bool
    {
        if (! $this->defaultPackage()) {
            return true;
        }

        return $this->feature($user, $key)?->isEnabled() ?? false;
    }

    /** Gói rẻ nhất đang bán mà mở được tính năng này — gợi ý nâng cấp trên paywall. */
    public function cheapestPackageAllowing(string $key): ?Package
    {
        return Package::active()
            ->where('price', '>', 0)
            ->whereHas('features', fn ($q) => $q->where('key', $key)->where('value', '1'))
            ->orderBy('price')
            ->first();
    }

    /**
     * Ngày gói mới bắt đầu: đang có gói CÙNG tier còn hạn thì nối sau ngày hết hạn đó (cộng dồn),
     * không thì bắt đầu ngay. Trang xác nhận mua dùng để báo trước cho người mua.
     */
    public function nextStartFor(User $beneficiary, Package $package, ?int $exceptId = null): Carbon
    {
        $latestEnd = Subscription::query()
            ->where('user_id', $beneficiary->id)
            ->where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '>', now())
            ->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))
            ->whereHas('package', fn ($q) => $q->where('tier', $package->tier))
            ->max('ends_at');

        return $latestEnd ? Carbon::parse($latestEnd) : now();
    }

    /** @return Collection<int, Subscription> */
    public function history(User $user): Collection
    {
        return Subscription::where('user_id', $user->id)->with('package', 'purchaser')->latest('id')->get();
    }

    // --- Ghi ------------------------------------------------------------------------------

    /**
     * Tạo đăng ký chờ thanh toán. Giá chụp từ DB tại thời điểm mua (§22: frontend không quyết định số tiền).
     */
    public function createPending(User $beneficiary, Package $package, ?User $payer = null): Subscription
    {
        if (! $package->is_active || $package->isFree()) {
            throw new RuntimeException('Gói này không mở bán.');
        }

        if (! $beneficiary->isStudent()) {
            throw new RuntimeException('Gói học chỉ áp dụng cho tài khoản học sinh.');
        }

        return Subscription::create([
            'user_id' => $beneficiary->id,
            'purchased_by' => ($payer ?? $beneficiary)->id,
            'package_id' => $package->id,
            'status' => Subscription::STATUS_PENDING,
            'price_paid' => $package->price,
            'duration_days' => $package->duration_days,
            'source' => 'payment',
        ]);
    }

    /**
     * Kích hoạt. Idempotent: gọi lại trên đăng ký đã active không cộng thêm ngày.
     * Đang có gói CÙNG tier còn hạn → nối tiếp sau ngày hết hạn đó (cộng dồn, §8 plan).
     */
    public function activate(Subscription $subscription): Subscription
    {
        return DB::transaction(function () use ($subscription) {
            /** @var Subscription $locked */
            $locked = Subscription::with('package', 'user')->whereKey($subscription->id)->lockForUpdate()->firstOrFail();

            if ($locked->status === Subscription::STATUS_ACTIVE) {
                return $locked;
            }

            if ($locked->status !== Subscription::STATUS_PENDING) {
                throw new RuntimeException('Không thể kích hoạt đăng ký ở trạng thái '.$locked->status.'.');
            }

            $starts = $this->nextStartFor($locked->user, $locked->package, $locked->id);

            $locked->update([
                'status' => Subscription::STATUS_ACTIVE,
                'starts_at' => $starts,
                'ends_at' => $starts->copy()->addDays((int) $locked->duration_days),
                'activated_at' => now(),
                // Gia hạn xong thì nhắc lại từ đầu cho chu kỳ mới.
                'expiry_reminded_days' => null,
            ]);

            self::$generation++;

            return $locked;
        });
    }

    /** Admin cấp gói tay (khuyến mãi, bù sự cố). Luôn ghi audit log. */
    public function grant(User $admin, User $student, Package $package, ?int $days = null): Subscription
    {
        if (! $student->isStudent()) {
            throw new RuntimeException('Chỉ cấp gói cho tài khoản học sinh.');
        }

        return DB::transaction(function () use ($admin, $student, $package, $days) {
            $subscription = Subscription::create([
                'user_id' => $student->id,
                'purchased_by' => $admin->id,
                'package_id' => $package->id,
                'status' => Subscription::STATUS_PENDING,
                'price_paid' => 0,
                'duration_days' => $days ?? $package->duration_days ?? 30,
                'source' => 'manual',
            ]);

            $this->activate($subscription);
            $this->audit->log('subscription.granted', $subscription, null, [
                'student_id' => $student->id, 'package' => $package->slug, 'days' => $subscription->duration_days,
            ]);

            return $subscription->refresh();
        });
    }

    /** Huỷ — mất quyền ngay. Dùng cho admin xử lý (hoàn tiền, gian lận). */
    public function cancel(Subscription $subscription, User $actor, string $reason): Subscription
    {
        $old = $subscription->status;

        $subscription->update([
            'status' => Subscription::STATUS_CANCELLED,
            'cancelled_at' => now(),
            'cancel_reason' => $reason,
        ]);

        self::$generation++;
        $this->audit->log('subscription.cancelled', $subscription, ['status' => $old], ['reason' => $reason, 'by' => $actor->id]);

        return $subscription;
    }

    /** Dọn trạng thái: đăng ký quá hạn → expired. Quyền truy cập vốn đã dựa vào ends_at. */
    public function expireDue(): int
    {
        return Subscription::where('status', Subscription::STATUS_ACTIVE)
            ->where('ends_at', '<=', now())
            ->update(['status' => Subscription::STATUS_EXPIRED]);
    }

    /** Đăng ký chờ thanh toán bỏ dở quá lâu → huỷ, để không tích rác. */
    public function cancelStalePending(int $hours = 24): int
    {
        return Subscription::where('status', Subscription::STATUS_PENDING)
            ->where('created_at', '<', now()->subHours($hours))
            ->update([
                'status' => Subscription::STATUS_CANCELLED,
                'cancelled_at' => now(),
                'cancel_reason' => 'Quá hạn thanh toán',
            ]);
    }

    public function forget(User $user): void
    {
        self::$generation++;
    }
}
