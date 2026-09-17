@extends('layouts.app', ['portal' => auth()->user()->isParent() ? 'parent' : 'student'])

@section('title', 'Giao dịch ' . $payment->order_code . ' — TOÁN AI')
@section('page_title', 'Kết quả thanh toán')

@php
    $tone = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'cancelled' => 'secondary'][$payment->status];
    $icon = ['paid' => 'bi-check-circle-fill', 'pending' => 'bi-hourglass-split', 'failed' => 'bi-x-circle-fill', 'cancelled' => 'bi-slash-circle'][$payment->status];
@endphp

@section('content')
    <div class="mx-auto" style="max-width:560px">
        {{-- Trạng thái đọc từ DB — không dựa vào tham số MoMo gắn trên URL trả về. --}}
        <div class="card border-{{ $tone }} mb-3" @if ($payment->isPending()) data-payment-poll="{{ route('payment.status', $payment) }}" @endif>
            <div class="card-body text-center p-4">
                <i class="bi {{ $icon }} text-{{ $tone }}" style="font-size:3rem"></i>

                @switch($payment->status)
                    @case('paid')
                        <h1 class="h4 fw-bold mt-2">Thanh toán thành công</h1>
                        <p class="mb-0">
                            Gói <strong>{{ $payment->package->name }}</strong> cho <strong>{{ $payment->subscription?->user->name }}</strong>
                            @if ($payment->subscription?->starts_at)
                                có hiệu lực {{ $payment->subscription->starts_at->isFuture() ? 'từ ' . $payment->subscription->starts_at->format('d/m/Y') : 'ngay' }}
                                đến {{ $payment->subscription->ends_at->format('d/m/Y') }}.
                            @endif
                        </p>
                        @break
                    @case('pending')
                        <h1 class="h4 fw-bold mt-2">Đang chờ xác nhận từ MoMo</h1>
                        <p class="text-secondary mb-0">Trang sẽ tự cập nhật. Nếu đã trừ tiền, gói sẽ được kích hoạt trong ít phút.</p>
                        @break
                    @case('failed')
                        <h1 class="h4 fw-bold mt-2">Thanh toán không thành công</h1>
                        <p class="text-secondary mb-0">{{ $payment->gateway_message ?: 'Giao dịch bị từ chối hoặc đã huỷ.' }} Bạn chưa bị trừ tiền cho đơn này.</p>
                        @break
                    @default
                        <h1 class="h4 fw-bold mt-2">Đơn đã huỷ</h1>
                        <p class="text-secondary mb-0">Đơn quá thời gian thanh toán. Bạn có thể tạo đơn mới.</p>
                @endswitch
            </div>
        </div>

        <div class="card border mb-3">
            <div class="card-body small d-grid gap-2">
                <div class="d-flex justify-content-between"><span class="text-secondary">Mã đơn</span><code>{{ $payment->order_code }}</code></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Gói</span><span>{{ $payment->package->name }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Số tiền</span><strong>{{ $payment->amountLabel() }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Phương thức</span><span>MoMo</span></div>
                @if ($payment->gateway_transaction_id)
                    <div class="d-flex justify-content-between"><span class="text-secondary">Mã giao dịch MoMo</span><span>{{ $payment->gateway_transaction_id }}</span></div>
                @endif
                <div class="d-flex justify-content-between"><span class="text-secondary">Thời gian</span><span>{{ ($payment->paid_at ?? $payment->created_at)->format('H:i d/m/Y') }}</span></div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if ($payment->isPending() && $payment->pay_url)
                <a href="{{ $payment->pay_url }}" class="btn btn-primary">Tiếp tục thanh toán</a>
            @elseif (! $payment->isPaid())
                <a href="{{ route('packages.checkout', $payment->package) }}" class="btn btn-primary">Thử lại</a>
            @endif
            <a href="{{ auth()->user()->isParent() ? route('parent.subscriptions.index') : route('student.subscription.index') }}" class="btn btn-outline-primary">Gói học</a>
            <a href="{{ route('payment.history') }}" class="btn btn-link">Lịch sử thanh toán</a>
        </div>
    </div>
@endsection

@push('scripts')
    <script>
        // Chờ IPN: hỏi trạng thái mỗi 3 giây trong tối đa 2 phút, đổi trạng thái thì tải lại trang.
        (() => {
            const el = document.querySelector('[data-payment-poll]');
            if (!el) return;
            let tries = 0;
            const timer = setInterval(async () => {
                if (++tries > 40) return clearInterval(timer);
                try {
                    const res = await fetch(el.dataset.paymentPoll, { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (json.data?.status && json.data.status !== 'pending') location.reload();
                } catch (e) { /* mạng chập chờn — thử lại lượt sau */ }
            }, 3000);
        })();
    </script>
@endpush
