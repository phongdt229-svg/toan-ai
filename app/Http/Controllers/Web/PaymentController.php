<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Package;
use App\Models\Payment;
use App\Services\Payment\Contracts\SimulatesPayments;
use App\Services\Payment\PaymentException;
use App\Services\Payment\PaymentGatewayManager;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PaymentController extends Controller
{
    public function __construct(private readonly PaymentService $payments) {}

    /**
     * Bấm "Thanh toán bằng MoMo/VNPAY" trên trang xác nhận. Số tiền không nhận từ form — lấy từ gói trong DB;
     * `method` chỉ là tên cổng, PaymentService từ chối cổng không bật.
     */
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
                $request->filled('method') ? (string) $request->input('method') : null,
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
     * Return URL của MoMo (`orderId`) và VNPAY (`vnp_TxnRef`). CHỈ dùng để tìm đơn — trạng thái đọc từ DB,
     * không tin các tham số kết quả trên URL (§8). IPN chưa tới thì trang đơn tự hỏi thẳng cổng.
     */
    public function handleReturn(Request $request): RedirectResponse
    {
        $orderCode = (string) ($request->query('orderId') ?? $request->query('vnp_TxnRef'));
        $payment = Payment::where('order_code', $orderCode)->first();

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

    // --- Giả lập cổng (chỉ khi PAYMENT_GATEWAY=fake, không bao giờ ở production) ---------------

    public function simulator(Request $request, Payment $payment, PaymentGatewayManager $gateways): View
    {
        $this->ensureSimulator($request, $payment, $gateways);

        return view('payment.simulator', ['payment' => $payment->load('package')]);
    }

    public function simulate(Request $request, Payment $payment, PaymentGatewayManager $gateways): RedirectResponse
    {
        $gateway = $this->ensureSimulator($request, $payment, $gateways);

        $payload = $gateway->simulatedNotification($payment, $request->input('result') === 'success');

        // Đi qua đúng hàm xử lý IPN thật (verify chữ ký, so tiền, idempotency).
        $this->payments->handleNotification($payload, $request->ip(), ['simulator' => true], $payment->method);

        return redirect()->route('payment.return', ['orderId' => $payment->order_code]);
    }

    private function ensureSimulator(Request $request, Payment $payment, PaymentGatewayManager $gateways): SimulatesPayments
    {
        abort_unless($gateways->usesFake(), 404);
        abort_unless($payment->isOwnedBy($request->user()), 404);

        try {
            $gateway = $gateways->get((string) $payment->method);
        } catch (PaymentException) {
            abort(404); // đơn mã giảm 100% không có cổng để giả lập
        }

        abort_unless($gateway instanceof SimulatesPayments, 404);

        return $gateway;
    }
}
