@extends('layouts.app', ['portal' => auth()->user()->isParent() ? 'parent' : 'student'])

@section('title', 'Giao dịch ' . $payment->order_code . ' — Math AI')
@section('page_title', 'Kết quả thanh toán')

@php
    $tone = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'cancelled' => 'secondary', 'refunded' => 'info'][$payment->status];
    $icon = ['paid' => 'bi-check-circle-fill', 'pending' => 'bi-hourglass-split', 'failed' => 'bi-x-circle-fill', 'cancelled' => 'bi-slash-circle', 'refunded' => 'bi-arrow-counterclockwise'][$payment->status];
@endphp

@section('content')
    <div class="mx-auto" style="max-width:560px">
        {{-- Trạng thái đọc từ DB — không dựa vào tham số của cổng gắn trên URL trả về. --}}
        {{-- Chuyển khoản QR: hỏi trạng thái suốt thời hạn đơn (khách cần vài phút mở app ngân hàng), cổng khác 2 phút. --}}
        <div class="card border-{{ $tone }} mb-3" @if ($payment->isPending()) data-payment-poll="{{ route('payment.status', $payment) }}" data-poll-max="{{ $bankQr ? (int) ceil(max(60, now()->diffInSeconds($payment->expires_at, false) + 300) / 3) : 40 }}" @endif>
            <div class="card-body text-center p-4">
                @if ($bankQr)
                    <h1 class="h5 fw-bold mb-1">Quét mã để chuyển khoản</h1>
                    <p class="small text-secondary mb-3">Mở app ngân hàng bất kỳ → Quét QR. Số tiền và nội dung đã điền sẵn — <strong>đừng sửa nội dung</strong>.</p>
                    <div class="d-inline-block bg-white p-2 rounded border mb-3" style="width:256px;max-width:100%" data-bank-qr>
                        {!! $bankQr['qr_svg'] !!}
                    </div>
                    <div class="text-start small d-grid gap-2 mx-auto" style="max-width:360px">
                        @foreach ([
                            ['Ngân hàng', $bankQr['bank_name'], false],
                            ['Số tài khoản', $bankQr['account_no'], true],
                            ['Chủ tài khoản', $bankQr['account_name'], false],
                            ['Số tiền', number_format($bankQr['amount'], 0, ',', '.') . '₫', false],
                            ['Nội dung', $bankQr['content'], true],
                        ] as [$label, $value, $copy])
                            <div class="d-flex justify-content-between align-items-center gap-2">
                                <span class="text-secondary">{{ $label }}</span>
                                <span class="d-flex align-items-center gap-1 text-end">
                                    <strong class="font-monospace text-break">{{ $value }}</strong>
                                    @if ($copy)
                                        <button type="button" class="btn btn-sm btn-link p-0" data-copy="{{ $value }}" aria-label="Sao chép {{ mb_strtolower($label) }}"><i class="bi bi-copy"></i></button>
                                    @endif
                                </span>
                            </div>
                        @endforeach
                    </div>
                    <p class="small text-secondary mt-3 mb-0">
                        <i class="bi bi-hourglass-split me-1"></i>Mã có hiệu lực tới {{ $payment->expires_at->format('H:i') }}.
                        Chuyển xong, trang tự cập nhật khi hệ thống nhận được tiền — thường trong 1–2 phút.
                    </p>
                @else
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
                        <h1 class="h4 fw-bold mt-2">Đang chờ xác nhận từ {{ $payment->methodLabel() }}</h1>
                        <p class="text-secondary mb-0">Trang sẽ tự cập nhật. Nếu đã trừ tiền, gói sẽ được kích hoạt trong ít phút.</p>
                        @break
                    @case('failed')
                        <h1 class="h4 fw-bold mt-2">Thanh toán không thành công</h1>
                        <p class="text-secondary mb-0">{{ $payment->gateway_message ?: 'Giao dịch bị từ chối hoặc đã huỷ.' }} Bạn chưa bị trừ tiền cho đơn này.</p>
                        @break
                    @case('refunded')
                        <h1 class="h4 fw-bold mt-2">Đơn đã được hoàn tiền</h1>
                        <p class="text-secondary mb-0">Số tiền đã được hoàn về {{ $payment->method === \App\Models\Payment::METHOD_MOMO ? 'ví MoMo' : 'tài khoản/thẻ đã thanh toán' }} của bạn và gói tương ứng đã được thu hồi.</p>
                        @break
                    @default
                        <h1 class="h4 fw-bold mt-2">Đơn đã huỷ</h1>
                        <p class="text-secondary mb-0">Đơn quá thời gian thanh toán. Bạn có thể tạo đơn mới.</p>
                        @if ($payment->method === \App\Models\Payment::METHOD_BANK_QR)
                            <p class="small text-secondary mt-2 mb-0">Đã chuyển khoản cho đơn này? Gửi yêu cầu hỗ trợ kèm mã đơn — chúng tôi sẽ đối chiếu và kích hoạt gói.</p>
                        @endif
                @endswitch
                @endif
            </div>
        </div>

        <div class="card border mb-3">
            <div class="card-body small d-grid gap-2">
                <div class="d-flex justify-content-between"><span class="text-secondary">Mã đơn</span><code>{{ $payment->order_code }}</code></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Gói</span><span>{{ $payment->package->name }}</span></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Số tiền</span><strong>{{ $payment->amountLabel() }}</strong></div>
                <div class="d-flex justify-content-between"><span class="text-secondary">Phương thức</span><span>{{ $payment->methodLabel() }}</span></div>
                @if ($payment->gateway_transaction_id)
                    <div class="d-flex justify-content-between"><span class="text-secondary">Mã giao dịch {{ $payment->methodLabel() }}</span><span>{{ $payment->gateway_transaction_id }}</span></div>
                @endif
                <div class="d-flex justify-content-between"><span class="text-secondary">Thời gian</span><span>{{ ($payment->paid_at ?? $payment->created_at)->format('H:i d/m/Y') }}</span></div>
            </div>
        </div>

        <div class="d-flex flex-wrap gap-2">
            @if ($bankQr)
                {{-- Đang ở chính trang thanh toán — không cần nút "Tiếp tục thanh toán". --}}
            @elseif ($payment->isPending() && $payment->pay_url)
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
        // Sao chép số tài khoản / nội dung chuyển khoản (gõ tay dễ sai là tiền không khớp được đơn).
        document.querySelectorAll('[data-copy]').forEach((btn) => btn.addEventListener('click', async () => {
            try { await navigator.clipboard.writeText(btn.dataset.copy); } catch (e) { return; }
            const icon = btn.querySelector('i');
            icon.className = 'bi bi-check2 text-success';
            setTimeout(() => { icon.className = 'bi bi-copy'; }, 1500);
        }));

        // Chờ IPN: hỏi trạng thái mỗi 3 giây (số lượt theo data-poll-max), đổi trạng thái thì tải lại trang.
        (() => {
            const el = document.querySelector('[data-payment-poll]');
            if (!el) return;
            const max = parseInt(el.dataset.pollMax || '40', 10);
            let tries = 0;
            const timer = setInterval(async () => {
                if (++tries > max) return clearInterval(timer);
                try {
                    const res = await fetch(el.dataset.paymentPoll, { headers: { Accept: 'application/json' } });
                    const json = await res.json();
                    if (json.data?.status && json.data.status !== 'pending') location.reload();
                } catch (e) { /* mạng chập chờn — thử lại lượt sau */ }
            }, 3000);
        })();
    </script>
@endpush
