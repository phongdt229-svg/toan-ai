@extends('layouts.app', ['portal' => auth()->user()->isParent() ? 'parent' : 'student'])

@section('title', 'Lịch sử thanh toán — TOÁN AI')
@section('page_title', 'Lịch sử thanh toán')

@section('content')
    <h2 class="h5 fw-bold mb-3">Lịch sử thanh toán</h2>

    @if ($payments->isEmpty())
        <div class="card border">
            <div class="card-body text-center text-secondary p-4">
                Chưa có giao dịch nào. <a href="{{ route('packages.index') }}">Xem các gói học</a>
            </div>
        </div>
    @else
        <div class="d-grid gap-2">
            @foreach ($payments as $payment)
                @php $tone = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'cancelled' => 'secondary'][$payment->status]; @endphp
                <a href="{{ route('payment.show', $payment) }}" class="card border text-decoration-none text-body">
                    <div class="card-body py-2 d-flex flex-wrap align-items-center gap-2">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $payment->package->name }}
                                @if ($payment->subscription && $payment->subscription->user_id != $payment->user_id)
                                    <span class="fw-normal text-secondary">· cho {{ $payment->subscription->user->name }}</span>
                                @endif
                            </div>
                            <div class="small text-secondary">{{ $payment->created_at->format('H:i d/m/Y') }} · {{ $payment->order_code }}</div>
                        </div>
                        <div class="text-end">
                            <div class="fw-semibold">{{ $payment->amountLabel() }}</div>
                            <span class="badge text-bg-{{ $tone }}">{{ $payment->statusLabel() }}</span>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>

        <div class="mt-3">{{ $payments->links() }}</div>
    @endif
@endsection
