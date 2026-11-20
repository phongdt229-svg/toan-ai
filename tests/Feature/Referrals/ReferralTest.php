<?php

namespace Tests\Feature\Referrals;

use App\Models\Grade;
use App\Models\Payment;
use App\Models\Referral;
use App\Models\ReferralSetting;
use App\Models\User;
use App\Models\Voucher;
use App\Notifications\ReferralRewarded;
use App\Services\Auth\AccountDeletionService;
use App\Services\Payment\PaymentService;
use App\Services\Payment\VoucherException;
use App\Services\Payment\VoucherService;
use App\Services\Referral\ReferralService;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

/** Giới thiệu bạn bè: link → đăng ký → mã chào mừng riêng; bạn trả tiền thật → thưởng người giới thiệu một lần. */
class ReferralTest extends SubscriptionTestCase
{
    private User $referrer;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'payment.gateway' => 'momo',
            'payment.methods' => ['momo', 'bank_qr'],
            'payment.bank_qr.bank_name' => 'ACB',
            'payment.bank_qr.bank_bin' => '970416',
            'payment.bank_qr.account_no' => '244556559',
            'payment.bank_qr.account_name' => 'TEST',
        ]);
        Notification::fake();

        $this->referrer = $this->makeStudent('Người Giới Thiệu');
    }

    private function referralLink(): string
    {
        return route('referral.link', app(ReferralService::class)->codeFor($this->referrer));
    }

    /** Đi đúng luồng thật: bấm link (cookie) → đăng ký học sinh. */
    private function signUpViaLink(string $email = 'banmoi@example.com'): User
    {
        // Trong test, cookie "xoá" mà lượt đăng ký trước xếp hàng vẫn nằm trong container và sẽ ghi đè cookie mới
        // cùng tên — ngoài đời mỗi request một tiến trình nên không có chuyện đó.
        app('cookie')->flushQueuedCookies();

        $cookie = $this->get($this->referralLink())->assertRedirect(route('register.student'))
            ->getCookie(ReferralService::COOKIE, false);

        $this->withUnencryptedCookie(ReferralService::COOKIE, $cookie?->getValue() ?? '')
            ->post(route('register.student'), [
                'name' => 'Bạn Mới', 'email' => $email,
                'grade_id' => Grade::where('level', 6)->value('id'),
                'password' => 'matkhau123', 'password_confirmation' => 'matkhau123',
            ])->assertSessionHasNoErrors();

        auth()->logout();

        return User::where('email', $email)->firstOrFail();
    }

    /** Mua gói bằng chuyển khoản QR rồi quản trị xác nhận → đi qua PaymentService::apply() thật. */
    private function pay(User $student, ?string $code = null): Payment
    {
        $payment = app(PaymentService::class)->checkout($student, $student, $this->package('pro-thang'), null, $code, 'bank_qr');

        return app(PaymentService::class)->confirmBankTransfer($payment, $this->makeAdmin(), 'FT'.$payment->id);
    }

    public function test_signup_via_link_records_referral_and_gives_a_welcome_voucher(): void
    {
        $friend = $this->signUpViaLink();

        $referral = Referral::where('referred_id', $friend->id)->firstOrFail();
        $this->assertSame($this->referrer->id, $referral->referrer_id);
        $this->assertSame($this->referrer->id, $friend->referred_by);

        $welcome = Voucher::findOrFail($referral->welcome_voucher_id);
        $this->assertSame($friend->id, $welcome->owner_id);
        $this->assertEquals(10, (float) $welcome->value);

        // Mã chào mừng giảm đúng 10% cho chính bạn mới…
        $quote = app(VoucherService::class)->quote($welcome->code, $this->package('pro-thang'), $friend);
        $this->assertEquals((int) $this->package('pro-thang')->price * 0.9, $quote->payable);

        // …nhưng người khác cầm mã cũng không dùng được.
        $this->expectException(VoucherException::class);
        app(VoucherService::class)->quote($welcome->code, $this->package('pro-thang'), $this->referrer);
    }

    public function test_first_real_payment_rewards_the_referrer_once(): void
    {
        $friend = $this->signUpViaLink();

        $this->pay($friend);

        $referral = Referral::where('referred_id', $friend->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_REWARDED, $referral->status);
        $reward = Voucher::findOrFail($referral->reward_voucher_id);
        $this->assertSame($this->referrer->id, $reward->owner_id);
        $this->assertEquals(20, (float) $reward->value);
        Notification::assertSentTo($this->referrer, ReferralRewarded::class);

        // Gia hạn lần hai không thưởng thêm.
        $this->pay($friend);
        $this->assertSame(1, Voucher::where('owner_id', $this->referrer->id)->count());
    }

    public function test_full_refund_voids_an_unused_reward(): void
    {
        $friend = $this->signUpViaLink();
        $payment = $this->pay($friend);

        app(PaymentService::class)->refund($payment->refresh(), $this->makeAdmin(), 'Khách đổi ý');

        $referral = Referral::where('referred_id', $friend->id)->firstOrFail();
        $this->assertSame(Referral::STATUS_VOID, $referral->status);
        $this->assertFalse(Voucher::findOrFail($referral->reward_voucher_id)->is_active);
    }

    public function test_reward_cap_and_disabled_program(): void
    {
        ReferralSetting::query()->update(['max_rewards_per_referrer' => 1]);

        $this->pay($this->signUpViaLink('a@example.com'));
        $this->pay($this->signUpViaLink('b@example.com'));

        $this->assertSame(2, Referral::count(), 'cả hai bạn phải được ghi nhận giới thiệu');
        $this->assertSame(2, Referral::where('status', Referral::STATUS_REWARDED)->count());
        $this->assertSame(1, Voucher::where('owner_id', $this->referrer->id)->count()); // hết suất: ghi nhận, không tặng mã

        // Tắt chương trình: link không gắn ai nữa.
        ReferralSetting::query()->update(['is_enabled' => false]);
        $late = $this->signUpViaLinkWithoutCookieCheck('c@example.com');
        $this->assertNull($late->referred_by);
    }

    public function test_self_and_unknown_codes_are_ignored(): void
    {
        $this->assertNull(app(ReferralService::class)->attach($this->referrer, $this->referrer->referral_code ?? app(ReferralService::class)->codeFor($this->referrer)));
        $this->assertNull(app(ReferralService::class)->attach($this->makeStudent(), 'KHONGCOMA'));
        $this->assertSame(0, Referral::count());
    }

    public function test_student_page_shows_link_and_owned_vouchers(): void
    {
        $friend = $this->signUpViaLink();
        $welcome = Voucher::where('owner_id', $friend->id)->firstOrFail();

        $this->actingAs($friend)->get(route('student.referrals.index'))
            ->assertOk()
            ->assertSee(route('referral.link', $friend->fresh()->referral_code ?? ''), false)
            ->assertSee($welcome->code);

        // Mã riêng không lộ ở danh sách mã công khai của trang Gói học.
        $welcome->update(['is_public' => true]);
        $this->assertFalse(app(VoucherService::class)->publicOffers()->contains('id', $welcome->id));
    }

    public function test_admin_changes_settings_and_students_cannot(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->get(route('admin.referrals.index'))->assertOk();
        $this->actingAs($admin)->put(route('admin.referrals.update'), [
            'is_enabled' => '1', 'new_user_percent' => 15, 'referrer_percent' => 15,
            'voucher_valid_days' => 30, 'max_rewards_per_referrer' => 5,
        ])->assertSessionHas('status');
        $this->assertSame(15, ReferralSetting::firstOrFail()->new_user_percent);

        $this->actingAs($admin)->put(route('admin.referrals.update'), [
            'new_user_percent' => 90, 'referrer_percent' => 15, 'voucher_valid_days' => 30, 'max_rewards_per_referrer' => 5,
        ])->assertSessionHasErrors('new_user_percent');

        $this->actingAs($this->referrer)->put(route('admin.referrals.update'), [
            'new_user_percent' => 50, 'referrer_percent' => 50, 'voucher_valid_days' => 30, 'max_rewards_per_referrer' => 5,
        ])->assertForbidden();
    }

    public function test_anonymised_account_loses_its_public_code(): void
    {
        app(ReferralService::class)->codeFor($this->referrer);
        $method = new \ReflectionMethod(AccountDeletionService::class, 'anonymise');
        $method->invoke(app(AccountDeletionService::class), $this->referrer);

        $this->assertNull($this->referrer->fresh()->referral_code);
    }

    /** Cookie vẫn có thể còn từ trước khi chương trình tắt — đặt tay để kiểm attach() tự chặn. */
    private function signUpViaLinkWithoutCookieCheck(string $email): User
    {
        $this->withUnencryptedCookie(ReferralService::COOKIE, $this->referrer->referral_code)
            ->post(route('register.student'), [
                'name' => 'Bạn Muộn', 'email' => $email,
                'grade_id' => Grade::where('level', 6)->value('id'),
                'password' => 'matkhau123', 'password_confirmation' => 'matkhau123',
            ])->assertSessionHasNoErrors();
        auth()->logout();

        return User::where('email', $email)->firstOrFail();
    }
}
