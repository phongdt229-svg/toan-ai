<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\User;
use App\Services\Payment\VoucherService;
use App\Services\SubscriptionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\View\View;

/**
 * Bảng giá (§18) và trang xác nhận mua. Giá luôn đọc từ bảng `packages`.
 * Học sinh mua cho chính mình; phụ huynh chọn con đã liên kết để mua.
 */
class PackageController extends Controller
{
    public function __construct(
        private readonly SubscriptionService $subscriptions,
        private readonly VoucherService $vouchers,
    ) {}

    public function index(Request $request): View|RedirectResponse
    {
        if ($redirect = $this->applyCodeFromLink($request)) {
            return $redirect;
        }

        $user = $request->user();

        return view('public.packages.index', [
            'tiers' => Package::catalog(),
            'currentTier' => $user?->isStudent() ? $this->subscriptions->tier($user) : null,
            'current' => $user?->isStudent() ? $this->subscriptions->effective($user) : null,
        ]);
    }

    public function checkout(Request $request, Package $package): View|RedirectResponse
    {
        abort_if(! $package->is_active || $package->isFree(), 404);

        if ($redirect = $this->applyCodeFromLink($request, $package)) {
            return $redirect;
        }

        $user = $request->user();
        abort_unless($user->isStudent() || $user->isParent(), 403, 'Gói học dành cho học sinh — phụ huynh có thể mua cho con.');

        $children = $user->isParent()
            ? $user->linkedChildren()->with('studentProfile.grade')->orderBy('name')->get()
            : collect();

        $beneficiary = $this->beneficiary($user, $request->integer('con') ?: null, $children);

        // Mã trong session được tính LẠI cho đúng gói này; mã không hợp lệ thì trả null,
        // không chặn người dùng mua — chỉ là không được giảm.
        $voucherCode = $request->session()->get(VoucherController::SESSION_KEY);
        $quote = $beneficiary ? $this->vouchers->quoteOrNull($voucherCode, $package, $user) : null;

        return view('public.packages.checkout', [
            'package' => $package->load('features'),
            'voucherCode' => $voucherCode,
            'quote' => $quote,
            'payer' => $user,
            'beneficiary' => $beneficiary,
            'children' => $children,
            'current' => $beneficiary ? $this->subscriptions->effective($beneficiary) : null,
            'startsAt' => $beneficiary ? $this->subscriptions->nextStartFor($beneficiary, $package) : null,
        ]);
    }

    /**
     * Mã giảm giá đi kèm link quảng cáo (`?ma=KHAIGIANG30`).
     *
     * Áp xong chuyển hướng về URL sạch: F5 hoặc bấm back không áp lại, và mã không nằm lại
     * trong thanh địa chỉ. Mã sai thì KHÔNG chặn — link quảng cáo hỏng không được cản đường mua hàng.
     */
    private function applyCodeFromLink(Request $request, ?Package $package = null): ?RedirectResponse
    {
        $code = trim($request->string('ma')->toString());

        if ($code === '') {
            return null;
        }

        // Dò mã qua GET phải bị chặn như qua POST — nhưng chỉ đếm khi thật sự có mã,
        // để người xem trang bình thường không bị vạ lây.
        $key = 'voucher-link:'.($request->user()?->id ?: $request->ip());

        if (RateLimiter::tooManyAttempts($key, 10)) {
            return redirect()->to($request->url())->with('error', 'Bạn thử mã hơi nhiều, đợi một phút rồi thử lại nhé.');
        }

        RateLimiter::hit($key, 60);

        $target = $package ?? Package::active()->where('price', '>', 0)->ordered()->first();
        $quote = $target ? $this->vouchers->quoteOrNull($code, $target, $request->user()) : null;

        if (! $quote) {
            return redirect()->to($request->url())->with('error', "Mã {$code} không dùng được — bạn vẫn mua gói bình thường nhé.");
        }

        $request->session()->put(VoucherController::SESSION_KEY, $quote->voucher->code);

        return redirect()->to($request->url())->with('status', "Đã áp mã {$quote->voucher->code} cho bạn.");
    }

    /**
     * Người được dùng gói. Phụ huynh chỉ mua được cho con đang liên kết — id lạ trên URL bị chặn.
     *
     * @param  Collection<int, User>  $children
     */
    public static function beneficiary(User $user, ?int $childId, Collection $children): ?User
    {
        if ($user->isStudent()) {
            return $user;
        }

        if ($childId === null) {
            return $children->count() === 1 ? $children->first() : null;
        }

        $child = $children->firstWhere('id', $childId);
        abort_unless($child, 403, 'Bạn chỉ mua gói được cho con đã liên kết.');

        return $child;
    }
}
