@extends('layouts.base')

@section('title', 'Giả lập MoMo — TOÁN AI')

@section('body')
    <main class="container py-5" style="max-width:480px">
        <div class="alert alert-warning small">
            <i class="bi bi-cone-striped me-1"></i>
            <strong>Môi trường dev</strong> — đây là trang giả lập MoMo (<code>PAYMENT_GATEWAY=fake</code>).
            Nút bên dưới gửi IPN có chữ ký đúng chuẩn MoMo vào hệ thống.
        </div>

        <div class="card border" style="border-color:#a50064!important">
            <div class="card-body text-center p-4">
                <div class="fw-bold fs-4 mb-1" style="color:#a50064">MoMo</div>
                <div class="text-secondary small mb-3">Đơn {{ $payment->order_code }}</div>
                <div class="mb-1">{{ $payment->package->name }}</div>
                <div class="display-6 fw-bold mb-4">{{ $payment->amountLabel() }}</div>

                @if ($payment->isPending())
                    <form method="POST" action="{{ route('payment.simulate', $payment) }}" class="d-grid gap-2">
                        @csrf
                        <button name="result" value="success" class="btn btn-lg text-white" style="background:#a50064">Xác nhận thanh toán</button>
                        <button name="result" value="cancel" class="btn btn-outline-secondary">Huỷ giao dịch</button>
                    </form>
                @else
                    <p class="text-secondary">Đơn này đã được xử lý ({{ $payment->statusLabel() }}).</p>
                    <a href="{{ route('payment.show', $payment) }}" class="btn btn-primary">Xem kết quả</a>
                @endif
            </div>
        </div>
    </main>
@endsection
