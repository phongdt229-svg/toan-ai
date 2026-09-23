<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\Web\ApplyVoucherRequest;
use App\Models\Package;
use App\Services\Payment\VoucherException;
use App\Services\Payment\VoucherService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Ô "Mã giảm giá" ở trang xác nhận mua gói (§8b).
 *
 * Session chỉ giữ **chuỗi mã**, không giữ số tiền — lúc tạo đơn `PaymentService` tính lại từ đầu.
 * Nhờ vậy mã hết hạn hay hết lượt giữa chừng cũng không tạo được đơn giá rẻ.
 */
class VoucherController extends Controller
{
    /** Khoá session giữ mã người dùng đang áp. */
    public const SESSION_KEY = 'checkout.voucher';

    public function __construct(private readonly VoucherService $vouchers) {}

    public function apply(ApplyVoucherRequest $request, Package $package): RedirectResponse
    {
        $data = $request->validated();

        try {
            $quote = $this->vouchers->quote($data['code'], $package, $request->user());
        } catch (VoucherException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        $request->session()->put(self::SESSION_KEY, $quote->voucher->code);

        return back()->with('status', 'Đã áp dụng mã '.$quote->voucher->code
            .' — giảm '.number_format($quote->discount, 0, ',', '.').'₫.');
    }

    public function remove(Request $request, Package $package): RedirectResponse
    {
        $request->session()->forget(self::SESSION_KEY);

        return back()->with('status', 'Đã gỡ mã giảm giá.');
    }
}
