<?php

namespace App\Services\Payment;

use App\Models\Package;
use App\Models\Payment;
use App\Models\User;
use App\Models\Voucher;
use App\Models\VoucherRedemption;
use Illuminate\Support\Facades\DB;

/**
 * Mã giảm giá (PROJECT_PLAN.md §8b).
 *
 * Quy tắc xuyên suốt: client chỉ gửi **chuỗi mã**, số tiền luôn tính lại ở đây từ giá trong DB.
 * Mọi lần tính đều kiểm lại toàn bộ điều kiện — giá hiển thị ở màn hình trước đó không được tin.
 *
 * Số lượt còn lại đếm từ `voucher_redemptions` chứ không từ cột đếm sẵn: đơn huỷ phải trả lại lượt,
 * và hai người bấm cùng lúc lên mã còn một lượt thì `lockForUpdate` mới phân xử được.
 */
class VoucherService
{
    /** MoMo không nhận đơn dưới 1.000đ — giảm xuống khoảng giữa 0 và mức này là bế tắc. */
    public const MIN_GATEWAY_AMOUNT = 1000;

    /**
     * Kiểm mã và tính số tiền được giảm, KHÔNG giữ chỗ. Dùng cho ô "Áp dụng mã" ở trang xác nhận.
     *
     * @throws VoucherException
     */
    public function quote(string $code, Package $package, User $user): VoucherQuote
    {
        $voucher = Voucher::with('packages')->where('code', Voucher::normalizeCode($code))->first();

        if (! $voucher) {
            throw new VoucherException('Mã giảm giá không tồn tại.');
        }

        return $this->evaluate($voucher, $package, $user);
    }

    /**
     * Như `quote()` nhưng mã sai thì trả về null thay vì nổ — dùng khi vẽ lại trang xác nhận
     * với mã đang lưu trong session: mã vừa hết hạn không nên chặn người dùng mua gói.
     */
    public function quoteOrNull(?string $code, Package $package, User $user): ?VoucherQuote
    {
        if ($code === null || trim($code) === '') {
            return null;
        }

        try {
            return $this->quote($code, $package, $user);
        } catch (VoucherException) {
            return null;
        }
    }

    /**
     * Giữ một lượt cho đơn vừa tạo. **Phải gọi bên trong transaction của `PaymentService::checkout`**
     * để việc kiểm lượt và việc tạo đơn cùng sống hoặc cùng chết.
     *
     * @throws VoucherException
     */
    public function hold(Voucher $voucher, Package $package, User $user, Payment $payment): VoucherRedemption
    {
        // Khoá dòng voucher: hai người cùng nhắm lượt cuối thì người sau phải đợi và sẽ thấy hết lượt.
        $locked = Voucher::whereKey($voucher->id)->lockForUpdate()->firstOrFail();
        $locked->setRelation('packages', $voucher->packages ?? $locked->packages()->get());

        $quote = $this->evaluate($locked, $package, $user);

        return VoucherRedemption::create([
            'voucher_id' => $locked->id,
            'user_id' => $user->id,
            'payment_id' => $payment->id,
            'discount_amount' => $quote->discount,
        ]);
    }

    /** Đơn trả tiền xong → lượt tính là đã dùng thật. */
    public function markRedeemed(Payment $payment): void
    {
        VoucherRedemption::where('payment_id', $payment->id)
            ->whereNull('redeemed_at')
            ->whereNull('released_at')
            ->update(['redeemed_at' => now()]);
    }

    /** Đơn hỏng / hết hạn → trả lượt về kho cho người khác dùng. */
    public function release(Payment $payment): void
    {
        VoucherRedemption::where('payment_id', $payment->id)
            ->whereNull('redeemed_at')
            ->whereNull('released_at')
            ->update(['released_at' => now()]);
    }

    /** Số lượt đang bị giữ hoặc đã dùng. */
    public function usedCount(Voucher $voucher): int
    {
        return $voucher->heldRedemptions()->count();
    }

    // --------------------------------------------------------------------------------------

    /**
     * Toàn bộ điều kiện của một mã, theo thứ tự dễ hiểu nhất cho người dùng.
     * Thông báo nói rõ lý do — chống dò mã đã có throttle ở route lo.
     *
     * @throws VoucherException
     */
    private function evaluate(Voucher $voucher, Package $package, User $user): VoucherQuote
    {
        if (! $voucher->is_active) {
            throw new VoucherException('Mã giảm giá đã ngừng sử dụng.');
        }

        if (! $voucher->hasStarted()) {
            throw new VoucherException('Mã này chưa tới ngày áp dụng ('.$voucher->starts_at->format('d/m/Y').').');
        }

        if ($voucher->hasEnded()) {
            throw new VoucherException('Mã giảm giá đã hết hạn.');
        }

        if (! $voucher->appliesTo($package)) {
            throw new VoucherException("Mã này không áp dụng cho gói {$package->name}.");
        }

        $price = (float) $package->price;

        if ($voucher->min_order_amount !== null && $price < (float) $voucher->min_order_amount) {
            throw new VoucherException('Mã chỉ áp dụng cho đơn từ '.number_format((float) $voucher->min_order_amount, 0, ',', '.').'₫.');
        }

        if ($voucher->max_uses !== null && $this->usedCount($voucher) >= $voucher->max_uses) {
            throw new VoucherException('Mã giảm giá đã hết lượt sử dụng.');
        }

        $mine = $voucher->heldRedemptions()->where('user_id', $user->id)->count();

        if ($mine >= $voucher->max_uses_per_user) {
            throw new VoucherException($voucher->max_uses_per_user === 1
                ? 'Bạn đã dùng mã này rồi.'
                : "Bạn đã dùng hết {$voucher->max_uses_per_user} lượt của mã này.");
        }

        $discount = $voucher->discountOn($price);
        $payable = $price - $discount;

        // Giảm xuống còn vài trăm đồng thì cổng từ chối — báo ngay thay vì để khách bấm rồi gặp lỗi lạ.
        if ($payable > 0 && $payable < self::MIN_GATEWAY_AMOUNT) {
            throw new VoucherException('Mã làm số tiền còn lại thấp hơn mức tối thiểu '
                .number_format(self::MIN_GATEWAY_AMOUNT, 0, ',', '.').'₫ mà cổng thanh toán nhận.');
        }

        return new VoucherQuote($voucher, $discount, $payable);
    }
}
