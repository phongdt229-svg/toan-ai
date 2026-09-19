<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Services\Payment\PaymentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/** Quản lý giao dịch (§25): tra cứu, xem log IPN, đối soát lại với cổng. */
class PaymentController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        return view('admin.payments.index', [
            'payments' => Payment::query()
                ->with('user', 'package', 'subscription.user')
                ->when(array_key_exists($status, Payment::STATUS_LABELS), fn ($q) => $q->where('status', $status))
                ->when($status === 'flagged', fn ($q) => $q->whereNotNull('flag_reason'))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('order_code', 'like', "%{$search}%")
                    ->orWhere('gateway_transaction_id', $search)
                    ->orWhereHas('user', fn ($u) => $u->where('email', 'like', "%{$search}%"))))
                ->latest('id')
                ->paginate(config('site.per_page'))
                ->withQueryString(),
            'status' => $status,
            'search' => $search,
            'stats' => [
                'revenue_month' => (int) Payment::where('status', Payment::STATUS_PAID)->where('paid_at', '>=', now()->startOfMonth())->sum('amount'),
                'paid_month' => Payment::where('status', Payment::STATUS_PAID)->where('paid_at', '>=', now()->startOfMonth())->count(),
                'flagged' => Payment::whereNotNull('flag_reason')->count(),
                'bad_signatures_7d' => PaymentWebhookLog::where('result', PaymentWebhookLog::RESULT_INVALID_SIGNATURE)
                    ->where('created_at', '>=', now()->subDays(7))->count(),
            ],
            'daily' => $this->dailyForChart(),
        ]);
    }

    /**
     * Chuỗi 14 ngày cho biểu đồ: số giao dịch thành công + doanh thu. Ngày không có giao dịch
     * vẫn có mặt (giá trị 0) để trục thời gian liền mạch.
     *
     * @return list<array{label: string, count: int, revenue: int}>
     */
    private function dailyForChart(): array
    {
        $since = today()->subDays(13);

        $rows = Payment::query()
            ->where('status', Payment::STATUS_PAID)
            ->whereDate('paid_at', '>=', $since)
            ->groupByRaw('DATE(paid_at)')
            ->selectRaw('DATE(paid_at) d, COUNT(*) c, SUM(amount) r')
            ->get()
            ->keyBy('d');

        return collect(range(0, 13))->map(function (int $i) use ($since, $rows) {
            $day = $since->copy()->addDays($i)->toDateString();
            $row = $rows->get($day);

            return [
                'label' => Carbon::parse($day)->format('d/m'),
                'count' => (int) ($row->c ?? 0),
                'revenue' => (int) ($row->r ?? 0),
            ];
        })->all();
    }

    public function show(Payment $payment): View
    {
        return view('admin.payments.show', [
            'payment' => $payment->load('user', 'package', 'subscription.user'),
            'logs' => PaymentWebhookLog::where('order_code', $payment->order_code)->latest('id')->get(),
        ]);
    }

    /** Hỏi lại cổng thanh toán — dùng khi người dùng báo "đã trừ tiền mà chưa có gói". */
    public function reconcile(Payment $payment, PaymentService $payments): RedirectResponse
    {
        $before = $payment->status;
        $payment = $payments->reconcile($payment);

        return back()->with('status', $payment->status === $before
            ? 'Cổng thanh toán chưa báo thay đổi trạng thái.'
            : "Đã cập nhật: {$payment->statusLabel()}.");
    }
}
