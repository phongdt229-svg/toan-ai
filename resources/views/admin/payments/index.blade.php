@extends('layouts.app', ['portal' => 'admin'])

@push('head')
    @vite('resources/js/charts.js')
@endpush

@section('title', 'Giao dịch — Quản trị Math AI')
@section('page_title', 'Giao dịch')

@section('content')
    <div class="row g-3 mb-3">
        @foreach ([
            ['Doanh thu tháng này', number_format($stats['revenue_month'], 0, ',', '.') . '₫', 'success'],
            ['Đơn thành công tháng này', $stats['paid_month'], 'primary'],
            ['Đơn cần xử lý tay', $stats['flagged'], $stats['flagged'] ? 'danger' : 'secondary'],
            ['IPN sai chữ ký (7 ngày)', $stats['bad_signatures_7d'], $stats['bad_signatures_7d'] ? 'danger' : 'secondary'],
            ['Chuyển khoản chưa khớp (30 ngày)', $stats['unmatched_bank'], $stats['unmatched_bank'] ? 'danger' : 'secondary'],
        ] as [$label, $value, $tone])
            <div class="col-6 col-lg">
                <div class="card border h-100"><div class="card-body py-2">
                    <div class="small text-secondary">{{ $label }}</div>
                    <div class="h5 fw-bold mb-0 text-{{ $tone }}">{{ $value }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    {{-- Tiền đã về tài khoản nhưng không khớp được đơn (khách sửa nội dung, chuyển thiếu/thừa) — phải xử lý tay. --}}
    @if ($unmatchedBank->isNotEmpty())
        <div class="card border-danger mb-3">
            <div class="card-body">
                <div class="fw-semibold mb-1"><i class="bi bi-bank me-1"></i>Chuyển khoản chưa khớp đơn</div>
                <p class="small text-secondary mb-2">
                    Tìm đơn theo email/nội dung, mở đơn rồi bấm <strong>Xác nhận đã nhận tiền</strong> (nhập mã tham chiếu bên dưới);
                    chuyển thiếu/thừa thì liên hệ khách trước.
                </p>
                <div class="table-responsive">
                    <table class="table table-sm align-middle small mb-0">
                        <thead><tr><th>Thời gian</th><th class="text-end">Số tiền</th><th>Nội dung</th><th>Mã tham chiếu</th><th>Lý do</th></tr></thead>
                        <tbody>
                            @foreach ($unmatchedBank as $log)
                                <tr>
                                    <td class="text-nowrap">{{ $log->created_at->format('H:i d/m') }}</td>
                                    <td class="text-end text-nowrap">{{ number_format((float) ($log->payload['transferAmount'] ?? 0), 0, ',', '.') }}₫</td>
                                    <td class="text-break">{{ \Illuminate\Support\Str::limit((string) ($log->payload['content'] ?? ''), 80) }}</td>
                                    <td class="font-monospace">{{ $log->payload['referenceCode'] ?? ($log->payload['id'] ?? '—') }}</td>
                                    <td>
                                        @if ($log->order_code)
                                            <a href="{{ route('admin.payments.show', $log->order_code) }}">{{ $log->result === 'amount_mismatch' ? 'Lệch số tiền' : 'Không thấy đơn' }}</a>
                                        @else
                                            Không có mã đơn trong nội dung
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    @endif

    <div class="card border mb-3">
        <div class="card-body">
            <div class="fw-semibold mb-2">14 ngày gần đây</div>
            <canvas data-chart-type="payments-daily" data-chart='@json($daily)'
                    role="img" aria-label="Biểu đồ số giao dịch thành công và doanh thu theo ngày"></canvas>
        </div>
    </div>

    <form method="GET" class="filter-bar">
        <input name="q" class="form-control" style="max-width:280px" placeholder="Mã đơn, mã giao dịch, email" value="{{ $search }}">
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
                    @php $tone = ['paid' => 'success', 'pending' => 'warning', 'failed' => 'danger', 'cancelled' => 'secondary', 'refunded' => 'info'][$payment->status]; @endphp
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
                        <td class="text-end">
                            {{ $payment->amountLabel() }}
                            @if ($payment->hasDiscount())
                                {{-- Kế toán nhìn đơn lệ giá phải biết vì sao. --}}
                                <div class="text-success small">
                                    −{{ number_format((float) $payment->discount_amount, 0, ',', '.') }}₫
                                    @if ($payment->voucher)<span class="font-monospace">{{ $payment->voucher->code }}</span>@endif
                                </div>
                            @endif
                        </td>
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

    <x-pagination :paginator="$payments" label="giao dịch" />
@endsection
