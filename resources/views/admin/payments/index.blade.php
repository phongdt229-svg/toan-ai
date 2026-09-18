@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Giao dịch — Quản trị TOÁN AI')
@section('page_title', 'Giao dịch')

@section('content')
    <div class="row g-3 mb-3">
        @foreach ([
            ['Doanh thu tháng này', number_format($stats['revenue_month'], 0, ',', '.') . '₫', 'success'],
            ['Đơn thành công tháng này', $stats['paid_month'], 'primary'],
            ['Đơn cần xử lý tay', $stats['flagged'], $stats['flagged'] ? 'danger' : 'secondary'],
            ['IPN sai chữ ký (7 ngày)', $stats['bad_signatures_7d'], $stats['bad_signatures_7d'] ? 'danger' : 'secondary'],
        ] as [$label, $value, $tone])
            <div class="col-6 col-lg-3">
                <div class="card border h-100"><div class="card-body py-2">
                    <div class="small text-secondary">{{ $label }}</div>
                    <div class="h5 fw-bold mb-0 text-{{ $tone }}">{{ $value }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <form method="GET" class="filter-bar">
        <input name="q" class="form-control" style="max-width:280px" placeholder="Mã đơn, mã MoMo, email" value="{{ $search }}">
        <select name="status" class="form-select" style="max-width:200px">
            <option value="">Mọi trạng thái</option>
            @foreach (\App\Models\Payment::STATUS_LABELS as $value => $label)
                <option value="{{ $value }}" @selected($status === $value)>{{ $label }}</option>
            @endforeach
            <option value="flagged" @selected($status === 'flagged')>Cần xử lý tay</option>
        </select>
        <button class="btn btn-outline-primary">Lọc</button>
    </form>

    <div class="table-responsive">
        <table class="table table-sm align-middle small">
            <thead>
                <tr><th>Mã đơn</th><th>Người trả</th><th>Gói</th><th class="text-end">Số tiền</th><th>Trạng thái</th><th>Thời gian</th></tr>
            </thead>
            <tbody>
                @forelse ($payments as $payment)
                    @php $tone = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'cancelled' => 'secondary'][$payment->status]; @endphp
                    <tr>
                        <td><a href="{{ route('admin.payments.show', $payment) }}"><code>{{ $payment->order_code }}</code></a></td>
                        <td>
                            <div>{{ $payment->user->name }}</div>
                            <div class="text-secondary">{{ $payment->user->email }}</div>
                        </td>
                        <td>
                            {{ $payment->package->name }}
                            @if ($payment->subscription && $payment->subscription->user_id != $payment->user_id)
                                <div class="text-secondary">cho {{ $payment->subscription->user->name }}</div>
                            @endif
                        </td>
                        <td class="text-end">{{ $payment->amountLabel() }}</td>
                        <td>
                            <span class="badge text-bg-{{ $tone }}">{{ $payment->statusLabel() }}</span>
                            @if ($payment->flag_reason)<span class="badge text-bg-danger" title="{{ $payment->flag_reason }}">Nghi vấn</span>@endif
                        </td>
                        <td class="text-nowrap">{{ ($payment->paid_at ?? $payment->created_at)->format('H:i d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Chưa có giao dịch.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{ $payments->links() }}
@endsection
