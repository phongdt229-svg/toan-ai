@extends('layouts.app', ['portal' => 'admin'])

@push('head')
    @vite('resources/js/charts.js')
@endpush

@php use App\Models\Voucher; @endphp

@section('title', 'Mã giảm giá — Quản trị TOÁN AI')
@section('page_title', 'Mã giảm giá')

@section('content')
    <div class="row g-3 mb-3">
        @foreach ([
            ['Tổng tiền đã giảm', number_format($summary['discount_total'], 0, ',', '.') . '₫', 'danger'],
            ['Lượt dùng thành công', number_format($summary['redemptions'], 0, ',', '.'), 'primary'],
            ['Doanh thu đơn có mã', number_format($summary['revenue_after'], 0, ',', '.') . '₫', 'success'],
            ['Tỉ lệ giảm trên giá gốc', $summary['discount_rate'] . '%', 'secondary'],
        ] as [$label, $value, $tone])
            <div class="col-6 col-lg-3">
                <div class="card border h-100"><div class="card-body py-2">
                    <div class="small text-secondary">{{ $label }}</div>
                    <div class="h5 fw-bold mb-0 text-{{ $tone }}">{{ $value }}</div>
                </div></div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-lg-8">
            <div class="card border h-100"><div class="card-body">
                <div class="fw-semibold mb-2">30 ngày gần đây</div>
                <canvas data-chart-type="vouchers-daily" data-chart='@json($daily)'
                        role="img" aria-label="Biểu đồ số lượt dùng mã và tiền đã giảm theo ngày"></canvas>
            </div></div>
        </div>
        <div class="col-12 col-lg-4">
            <div class="card border h-100"><div class="card-body">
                <div class="fw-semibold mb-2">Mã dùng nhiều nhất</div>
                @if ($top)
                    <canvas data-chart-type="voucher-top" data-chart='@json($top)'
                            role="img" aria-label="Biểu đồ số lượt dùng của từng mã"></canvas>
                @else
                    <div class="text-secondary small">Chưa có lượt dùng nào.</div>
                @endif
            </div></div>
        </div>
    </div>

    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="table-meta mb-0">{{ number_format($vouchers->total(), 0, ',', '.') }} mã</div>
        <a href="{{ route('admin.vouchers.create') }}" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>Tạo mã
        </a>
    </div>

    <div class="table-responsive">
        <table class="table table-sm align-middle small">
            <thead>
                <tr>
                    <th>Mã</th><th>Giảm</th><th>Áp dụng cho</th><th>Hiệu lực</th>
                    <th class="text-end">Đã dùng</th><th class="text-end">Đã giảm</th><th>Trạng thái</th><th></th>
                </tr>
            </thead>
            <tbody>
                @forelse ($vouchers as $voucher)
                    <tr>
                        <td>
                            <a href="{{ route('admin.vouchers.show', $voucher) }}"
                               class="fw-semibold font-monospace text-decoration-none">{{ $voucher->code }}</a>
                            @if ($voucher->description)
                                <div class="text-secondary">{{ $voucher->description }}</div>
                            @endif
                        </td>
                        <td>
                            {{ $voucher->valueLabel() }}
                            @if ($voucher->max_discount)
                                <div class="text-secondary">tối đa {{ number_format((float) $voucher->max_discount, 0, ',', '.') }}₫</div>
                            @endif
                            @if ($voucher->min_order_amount)
                                <div class="text-secondary">đơn từ {{ number_format((float) $voucher->min_order_amount, 0, ',', '.') }}₫</div>
                            @endif
                        </td>
                        <td>{{ $voucher->packages->isEmpty() ? 'Mọi gói' : $voucher->packages->pluck('name')->implode(', ') }}</td>
                        <td class="text-nowrap">
                            {{ $voucher->starts_at?->format('d/m/Y') ?? '—' }} →
                            {{ $voucher->ends_at?->format('d/m/Y') ?? 'không hạn' }}
                        </td>
                        <td class="text-end">
                            {{ $voucher->used_count }}{{ $voucher->max_uses ? '/' . $voucher->max_uses : '' }}
                            @if ($voucher->used_count > $voucher->paid_count)
                                {{-- Chênh lệch = lượt đang giữ chỗ cho đơn chưa trả tiền xong. --}}
                                <div class="text-secondary">{{ $voucher->used_count - $voucher->paid_count }} đang chờ</div>
                            @endif
                        </td>
                        <td class="text-end">{{ number_format((float) ($voucher->discount_total ?? 0), 0, ',', '.') }}₫</td>
                        <td>
                            @if (! $voucher->is_active)
                                <span class="badge text-bg-secondary">Đã tắt</span>
                            @elseif ($voucher->hasEnded())
                                <span class="badge text-bg-warning">Hết hạn</span>
                            @elseif (! $voucher->hasStarted())
                                <span class="badge text-bg-info">Chưa tới ngày</span>
                            @elseif ($voucher->max_uses && $voucher->used_count >= $voucher->max_uses)
                                <span class="badge text-bg-warning">Hết lượt</span>
                            @else
                                <span class="badge text-bg-success">Đang chạy</span>
                            @endif
                        </td>
                        <td class="text-end text-nowrap">
                            <a href="{{ route('admin.vouchers.edit', $voucher) }}" class="btn btn-sm btn-outline-secondary">Sửa</a>
                            @if ($voucher->used_count === 0 && $voucher->paid_count === 0)
                                <form method="POST" action="{{ route('admin.vouchers.destroy', $voucher) }}" class="d-inline"
                                      data-confirm="Xoá mã {{ $voucher->code }}?" data-confirm-ok="Xoá">
                                    @csrf
                                    @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Xoá</button>
                                </form>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="text-center text-secondary py-4">
                            Chưa có mã giảm giá nào.
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$vouchers" label="mã" />
@endsection
