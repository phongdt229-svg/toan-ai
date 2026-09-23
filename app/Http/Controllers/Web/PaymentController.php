<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Gateways\MomoGateway;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /** Bấm "Thanh toán MoMo" trên trang xác nhận. Số tiền không nhận từ form — lấy từ gói trong DB. */
    public function store(Request $request, Package $package): RedirectResponse
    {
        abort_if(! $package->is_active || $package->isFree(), 404);

        $user = $request->user();
        abort_unless($user->isStudent() || $user->isParent(), 403);

        $children = $user->isParent() ? $user->linkedChildren()->get() : collect();
        $beneficiary = PackageController::beneficiary($user, $request->integer('con') ?: null, $children);

        if (! $beneficiary) {
            return back()->with('error', 'Hãy chọn con để mua gói.');
        }

        try {
            $payment = $this->payments->checkout(
                $user,
                $beneficiary,
                $package,
                $request->ip(),
                $request->session()->get(VoucherController::SESSION_KEY),
            );
        } catch (PaymentException $e) {
            return back()->with('error', $e->getMessage());
        }

        $request->session()->forget(VoucherController::SESSION_KEY);

        // Mã giảm 100% → đơn đã thanh toán xong ngay, không có pay_url để đi tiếp.
        if ($payment->isPaid()) {
            return redirect()->route('payment.show', $payment);
        }

        return redirect()->away($payment->pay_url);
    }

    /**
     * Return URL của MoMo. CHỈ hiển thị trạng thái đọc từ DB — không tin query string (§8).
     * Nếu IPN chưa tới thì hỏi thẳng MoMo một lần (localhost không nhận được IPN).
     */
    public function handleReturn(Request $request): RedirectResponse
    {
        $payment = Payment::where('order_code', (string) $request->query('orderId'))->first();

        if (! $payment?->isOwnedBy($request->user())) {
            return redirect()->route('payment.history')->with('error', 'Không tìm thấy giao dịch.');
        }

        return redirect()->route('payment.show', $payment);
    }

    public function show(Request $request, Payment $payment): View
    {
        abort_unless($payment->isOwnedBy($request->user()), 404);

        if ($payment->isPending()) {
            $payment = $this->payments->reconcile($payment);
        }

        return view('payment.show', ['payment' => $payment->load('package', 'subscription.user')]);
    }

    /** Trang kết quả hỏi định kỳ trong lúc chờ IPN. */
    public function status(Request $request, Payment $payment): JsonResponse
    {
        abort_unless($payment->isOwnedBy($request->user()), 404);

        return response()->json(['success' => true, 'data' => ['status' => $payment->status]]);
    }

    public function history(Request $request): View
    {
        return view('payment.history', [
            'payments' => Payment::where('user_id', $request->user()->id)
                ->with('package', 'subscription.user')
                ->latest('id')
                ->paginate(20),
        ]);
    }

    // --- Giả lập MoMo (chỉ khi PAYMENT_GATEWAY=fake, không bao giờ ở production) ---------------

    public function simulator(Request $request, Payment $payment): View
    {
        $this->ensureSimulator($request, $payment);

        return view('payment.simulator', ['payment' => $payment->load('package')]);
    }

    public function simulate(Request $request, Payment $payment, PaymentGatewayInterface $gateway): RedirectResponse
    {
        $this->ensureSimulator($request, $payment);
        /** @var MomoGateway $gateway */
        $success = $request->input('result') === 'success';

        $payload = [
            'partnerCode' => $gateway->partnerCode(),
            'orderId' => $payment->order_code,
            'requestId' => (string) $payment->gateway_request_id,
            'amount' => $payment->amountInt(),
            'orderInfo' => 'Gia lap',
            'orderType' => 'momo_wallet',
            'transId' => $success ? (string) random_int(1_000_000_000, 9_999_999_999) : '',
            'resultCode' => $success ? 0 : 1006,
            'message' => $success ? 'Thành công.' : 'Người dùng đã từ chối xác nhận thanh toán.',
            'payType' => 'qr',
            'responseTime' => now()->getTimestampMs(),
            'extraData' => '',
        ];
        $payload['signature'] = $gateway->signNotification($payload);

        // Đi qua đúng hàm xử lý IPN thật (verify chữ ký, so tiền, idempotency).
        $this->payments->handleNotification($payload, $request->ip(), ['simulator' => true]);

        return redirect()->route('payment.return', ['orderId' => $payment->order_code, 'resultCode' => $payload['resultCode']]);
    }

    private function ensureSimulator(Request $request, Payment $payment): void
    {
        abort_if(config('payment.gateway') !== 'fake' || app()->environment('production'), 404);
        abort_unless($payment->isOwnedBy($request->user()), 404);
    }
}
