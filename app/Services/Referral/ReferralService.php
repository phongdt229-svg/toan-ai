<?php

namespace App\Services\Referral;

use App\Models\Payment;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\ReferralRewarded;
use App\Services\AuditLogger;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Giới thiệu bạn bè — mọi luật nằm ở đây.
 *
 * - Link `/gioi-thieu/{mã}` chỉ đặt cookie chứa MÃ; quan hệ được ghi lúc tài khoản học sinh MỚI đăng ký.
 * - Bạn mới nhận ngay mã giảm riêng cho một đơn. Người giới thiệu chỉ được thưởng khi bạn đó trả TIỀN THẬT
 *   (sự kiện PaymentCompleted), đúng một lần cho mỗi bạn, tối đa `max_rewards_per_referrer` lần.
 * - Đơn đó bị hoàn đủ tiền → mã thưởng chưa dùng bị tắt, giới thiệu chuyển "huỷ".
 * - Mọi phần thưởng là mã giảm giá RIÊNG (`vouchers.owner_id`) đi qua VoucherService như mã thường.
 */
class ReferralService
{
    public const COOKIE = 'math_ref';

    public const COOKIE_DAYS = 30;

    public function __construct(private readonly AuditLogger $audit) {}

    public function settings(): ReferralSetting
    {
        return ReferralSetting::current();
    }

    public function enabled(): bool
    {
        return $this->settings()->is_enabled;
    }

    /** Mã giới thiệu của học sinh — sinh lần đầu khi cần, không đổi về sau (link đã chia sẻ không được gãy). */
    public function codeFor(User $user): string
    {
        if ($user->referral_code) {
            return $user->referral_code;
        }

        do {
            // Bỏ chữ dễ nhầm (0/O, 1/I/L) — mã hay được đọc qua điện thoại / Zalo.
            $code = 'BAN'.substr(str_shuffle(str_repeat('ABCDEFGHJKMNPQRSTUVWXYZ23456789', 3)), 0, 6);
        } while (User::withTrashed()->where('referral_code', $code)->exists());

        $user->forceFill(['referral_code' => $code])->save();

        return $code;
    }

    public function referrerByCode(?string $code): ?User
    {
        $code = strtoupper(trim((string) $code));
        if ($code === '' || ! preg_match('/^[A-Z0-9]{4,20}$/', $code)) {
            return null;
        }

        $user = User::where('referral_code', $code)->where('status', User::STATUS_ACTIVE)->first();

        return $user && $user->isStudent() ? $user : null;
    }

    /**
     * Gọi ngay sau khi tài khoản học sinh mới được tạo. Mã sai / chương trình tắt / tự giới thiệu → bỏ qua im lặng
     * (người đăng ký không cần biết cookie giới thiệu có hợp lệ hay không).
     */
    public function attach(User $newUser, ?string $code): ?Referral
    {
        if (! $this->enabled() || ! $newUser->isStudent()) {
            return null;
        }

        $referrer = $this->referrerByCode($code);
        if (! $referrer || $referrer->id === $newUser->id || Referral::where('referred_id', $newUser->id)->exists()) {
            return null;
        }

        return DB::transaction(function () use ($newUser, $referrer) {
            $settings = $this->settings();
            $welcome = $settings->new_user_percent > 0
                ? $this->personalVoucher($newUser, $settings->new_user_percent, 'Quà chào mừng — bạn được '.$referrer->name.' giới thiệu')
                : null;

            $newUser->forceFill(['referred_by' => $referrer->id])->save();

            $referral = Referral::create([
                'referrer_id' => $referrer->id,
                'referred_id' => $newUser->id,
                'status' => Referral::STATUS_PENDING,
                'welcome_voucher_id' => $welcome?->id,
            ]);

            $this->audit->log('referral.joined', $referral, null, ['referrer_id' => $referrer->id]);

            return $referral;
        });
    }

    /** Đơn trả tiền thật đầu tiên của người được giới thiệu → thưởng người giới thiệu (một lần). */
    public function onPaymentCompleted(Payment $payment): void
    {
        if ($payment->amountInt() <= 0 || ! $this->enabled()) {
            return;
        }

        DB::transaction(function () use ($payment) {
            $referral = Referral::where('referred_id', $payment->user_id)
                ->where('status', Referral::STATUS_PENDING)
                ->lockForUpdate()
                ->first();

            if (! $referral) {
                return;
            }

            $settings = $this->settings();
            $rewarded = Referral::where('referrer_id', $referral->referrer_id)->where('status', Referral::STATUS_REWARDED)->count();
            $referrer = User::find($referral->referrer_id);

            // Hết suất thưởng hoặc người giới thiệu đã xoá tài khoản: vẫn ghi nhận đã mua, chỉ không tặng mã.
            $reward = $referrer && $rewarded < $settings->max_rewards_per_referrer && $settings->referrer_percent > 0
                ? $this->personalVoucher($referrer, $settings->referrer_percent, 'Thưởng giới thiệu bạn mới')
                : null;

            $referral->update([
                'status' => Referral::STATUS_REWARDED,
                'reward_voucher_id' => $reward?->id,
                'payment_id' => $payment->id,
                'rewarded_at' => now(),
            ]);

            $this->audit->log('referral.rewarded', $referral, null, ['payment_id' => $payment->id, 'voucher_id' => $reward?->id]);

            if ($reward) {
                DB::afterCommit(fn () => $referrer->notify(new ReferralRewarded($reward)));
            }
        });
    }

    /** Đơn được dùng để thưởng bị hoàn đủ tiền → thu lại mã thưởng nếu chưa dùng. */
    public function onPaymentRefunded(Payment $payment): void
    {
        DB::transaction(function () use ($payment) {
            $referral = Referral::where('payment_id', $payment->id)->lockForUpdate()->first();
            if (! $referral || $referral->status !== Referral::STATUS_REWARDED) {
                return;
            }

            if ($referral->reward_voucher_id) {
                $voucher = Voucher::lockForUpdate()->find($referral->reward_voucher_id);
                $used = $voucher && $voucher->redemptions()->whereNotNull('redeemed_at')->exists();
                if ($voucher && ! $used) {
                    $voucher->update(['is_active' => false]);
                }
            }

            $referral->update(['status' => Referral::STATUS_VOID]);
            $this->audit->log('referral.voided', $referral, null, ['payment_id' => $payment->id]);
        });
    }

    /** @return array{invited: int, rewarded: int, vouchers: Collection<int, Voucher>} */
    public function summaryFor(User $user): array
    {
        return [
            'invited' => Referral::where('referrer_id', $user->id)->count(),
            'rewarded' => Referral::where('referrer_id', $user->id)->where('status', Referral::STATUS_REWARDED)->count(),
            // Mã riêng còn dùng được (chào mừng + thưởng), mới nhất trước.
            'vouchers' => Voucher::where('owner_id', $user->id)
                ->where('is_active', true)
                ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
                ->whereDoesntHave('redemptions', fn ($q) => $q->whereNull('released_at'))
                ->latest('id')
                ->get(),
        ];
    }

    private function personalVoucher(User $owner, int $percent, string $description): Voucher
    {
        do {
            $code = 'GT'.strtoupper(Str::random(8));
        } while (Voucher::where('code', $code)->exists());

        return Voucher::create([
            'code' => $code,
            'description' => $description,
            'type' => Voucher::TYPE_PERCENT,
            'value' => $percent,
            'starts_at' => now(),
            'ends_at' => now()->addDays($this->settings()->voucher_valid_days),
            'max_uses' => 1,
            'max_uses_per_user' => 1,
            'is_active' => true,
            'is_public' => false,
            'owner_id' => $owner->id,
        ]);
    }
}
