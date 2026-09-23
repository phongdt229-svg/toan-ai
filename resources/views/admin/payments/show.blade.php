@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Đơn ' . $payment->order_code . ' — Quản trị TOÁN AI')
@section('page_title', 'Chi tiết giao dịch')

@section('content')
    <a href="{{ route('admin.payments.index') }}" class="small text-decoration-none"><i class="bi bi-arrow-left me-1"></i>Giao dịch</a>

    <div class="d-flex flex-wrap align-items-center gap-2 mt-2 mb-3">
        <h2 class="h5 fw-bold mb-0"><code>{{ $payment->order_code }}</code></h2>
        <span class="badge text-bg-{{ ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'cancelled' => 'secondary', 'refunded' => 'info'][$payment->status] }}">{{ $payment->statusLabel() }}</span>
        @if (! in_array($payment->status, ['paid', 'refunded'], true))
            <form method="POST" action="{{ route('admin.payments.reconcile', $payment) }}" class="ms-auto">
                @csrf
                <button class="btn btn-sm btn-outline-primary"><i class="bi bi-arrow-repeat me-1"></i>Đối soát với MoMo</button>
            </form>
        @endif
    </div>

    @if ($payment->flag_reason)
        <div class="alert alert-danger small">
            <i class="bi bi-exclamation-octagon me-1"></i><strong>Cần xử lý tay:</strong> {{ $payment->flag_reason }}.
            Hệ thống không tự cấp gói — kiểm tra trên cổng MoMo rồi cấp tay ở trang Đăng ký gói nếu hợp lệ.
        </div>
    @endif

    <div class="row g-3 mb-4">
        <div class="col-12 col-lg-6">
            <div class="card border h-100"><div class="card-body small d-grid gap-2">
                <div class="d-flex justify-content-between"><span class="text-secondary">Người trả</span><span>{{ $payment->user->name }} · {{ $payment->user->email }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Người dùng gói</span><span>{{ $payment->subscription?->user->name ?? '—' }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Gói</span><span>{{ $payment->package->name }}</span></div>
                @if ($payment->hasDiscount())
                    <div class="d-flex justify-content-between">
                        <span class="text-secondary">Giá gốc</span>
                        <span>{{ number_format($payment->originalAmount(), 0, ',', '.') }}₫</span>
                    </div>
                    <div class="d-flex justify-content-between text-success">
                        <span>Giảm giá @if ($payment->voucher)<span class="font-monospace">{{ $payment->voucher->code }}</span>@endif</span>
                        <span>−{{ number_format((float) $payment->discount_amount, 0, ',', '.') }}₫</span>
                    </div>
                @endif
                <div class="d-flex justify-content-between"><span class="text-secondary">Số tiền thực trả</span><strong>{{ $payment->amountLabel() }}</strong></div>
                @if ((float) $payment->refunded_amount > 0)
                    <div class="d-flex justify-content-between text-danger">
                        <span>Đã hoàn</span><span>−{{ number_format((float) $payment->refunded_amount, 0, ',', '.') }}₫</span>
                    </div>
                @endif
                <div class="d-flex justify-content-between"><span class="text-secondary">Đăng ký</span><span>#{{ $payment->subscription_id }} · {{ $payment->subscription?->statusLabel() }}</span></div>
            </div></div>
        </div>
        <div class="col-12 col-lg-6">
            <div class="card border h-100"><div class="card-body small d-grid gap-2">
                <div class="d-flex justify-content-between"><span class="text-secondary">Mã giao dịch MoMo</span><span>{{ $payment->gateway_transaction_id ?? '—' }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">resultCode</span><span>{{ $payment->gateway_result_code ?? '—' }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Thông điệp</span><span>{{ $payment->gateway_message ?? '—' }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Tạo lúc</span><span>{{ $payment->created_at->format('H:i:s d/m/Y') }} · IP {{ $payment->client_ip ?? '—' }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Thanh toán lúc</span><span>{{ $payment->paid_at?->format('H:i:s d/m/Y') ?? '—' }}</span></div>
            </div></div>
        </div>
    </div>

    @php
        // Còn dòng hoàn tiền `pending` = đã gửi MoMo mà chưa rõ kết quả → khoá nút, admin phải kiểm tra bên MoMo.
        $blockingRefund = $payment->refunds->first(fn ($r) => $r->status === 'pending');
        $canRefund = $payment->isPaid() && $payment->method !== 'voucher' && $payment->refundableInt() > 0 && ! $blockingRefund;
    @endphp

    @if ($payment->refunds->isNotEmpty() || $canRefund)
        <div class="card border mb-4">
            <div class="card-body">
                <h3 class="h6 fw-bold">Hoàn tiền</h3>

                @if ($blockingRefund)
                    <div class="alert alert-warning small">
                        Có yêu cầu hoàn <code>{{ $blockingRefund->refund_code }}</code> đã gửi nhưng chưa rõ kết quả
                        ({{ $blockingRefund->gateway_message ?: 'chưa có phản hồi' }}). Kiểm tra trên cổng MoMo —
                        hệ thống khoá nút để tránh hoàn hai lần.
                    </div>
                @endif

                @foreach ($payment->refunds as $refund)
                    <div class="small d-flex flex-wrap gap-2 align-items-center border-bottom py-1">
                        <span class="badge text-bg-{{ ['succeeded' => 'success', 'failed' => 'danger', 'pending' => 'warning'][$refund->status] }}">{{ \App\Models\PaymentRefund::STATUS_LABELS[$refund->status] }}</span>
                        <code>{{ $refund->refund_code }}</code>
                        <span>{{ number_format((float) $refund->amount, 0, ',', '.') }}₫</span>
                        <span class="text-secondary">{{ $refund->reason }}</span>
                        <span class="text-secondary ms-auto">{{ $refund->requester?->name }} · {{ $refund->created_at->format('H:i d/m/Y') }}</span>
                    </div>
                @endforeach

                @if ($canRefund)
                    <form method="POST" action="{{ route('admin.payments.refund', $payment) }}" class="d-flex flex-column flex-sm-row gap-2 mt-3"
                          data-confirm="Hoàn tiền cho đơn này? Không hoàn tác được." data-confirm-ok="Hoàn tiền">
                        @csrf
                        <input name="amount" type="number" min="1" max="{{ $payment->refundableInt() }}" step="1" inputmode="numeric"
                               class="form-control @error('amount') is-invalid @enderror" style="max-width:190px"
                               placeholder="Số tiền (để trống = {{ number_format($payment->refundableInt(), 0, ',', '.') }}₫)" aria-label="Số tiền hoàn">
                        <input name="reason" class="form-control" maxlength="191" required placeholder="Lý do hoàn tiền (ghi vào audit log)">
                        <button class="btn btn-danger flex-shrink-0"><i class="bi bi-arrow-counterclockwise me-1"></i>Hoàn tiền</button>
                    </form>
                    @error('amount')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    <div class="form-text">
                        Còn hoàn được {{ number_format($payment->refundableInt(), 0, ',', '.') }}₫.
                        Hoàn toàn bộ thì gói bị thu hồi; hoàn một phần thì gói được giữ nguyên.
                    </div>
                @endif
            </div>
        </div>
    @endif

    <h3 class="h6 fw-bold">Log IPN / đối soát</h3>
    @forelse ($logs as $log)
        <details class="card border mb-2">
            <summary class="card-body py-2 small d-flex flex-wrap gap-2 align-items-center">
                <span class="badge text-bg-{{ $log->result === 'processed' ? 'success' : ($log->result === 'duplicate' ? 'secondary' : 'warning') }}">{{ $log->result ?? '—' }}</span>
                <span>{{ $log->provider }}</span>
                <span class="{{ $log->signature_valid ? 'text-success' : 'text-danger' }}">{{ $log->signature_valid ? 'chữ ký hợp lệ' : 'chữ ký sai' }}</span>
                <span class="text-secondary ms-auto">{{ $log->created_at->format('H:i:s d/m/Y') }} · {{ $log->ip_address }}</span>
            </summary>
            <pre class="small bg-light p-2 m-0" style="white-space:pre-wrap">{{ json_encode($log->payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) }}</pre>
        </details>
    @empty
        <p class="text-secondary small">Chưa nhận IPN nào cho đơn này.</p>
    @endforelse
@endsection
