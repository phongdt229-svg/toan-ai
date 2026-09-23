@extends('layouts.app', ['portal' => 'admin'])

@php use App\Models\Payment; @endphp

@section('title', 'Mã ' . $voucher->code . ' — Quản trị TOÁN AI')
@section('page_title', 'Mã ' . $voucher->code)

@section('content')
    <div class="d-flex justify-content-between align-items-center mb-3">
        <a href="{{ route('admin.vouchers.index') }}" class="small text-decoration-none">
            <i class="bi bi-arrow-left me-1"></i>Mã giảm giá
        </a>
        <a href="{{ route('admin.vouchers.edit', $voucher) }}" class="btn btn-sm btn-outline-secondary">Sửa mã</a>
    </div>

    <div class="card border mb-4">
        <div class="card-body">
            <dl class="row small mb-0">
                <dt class="col-5 col-md-3 fw-normal text-secondary">Giảm</dt>
                <dd class="col-7 col-md-9">
                    {{ $voucher->valueLabel() }}
                    @if ($voucher->max_discount)
                        · tối đa {{ number_format((float) $voucher->max_discount, 0, ',', '.') }}₫
                    @endif
                    @if ($voucher->min_order_amount)
                        · đơn từ {{ number_format((float) $voucher->min_order_amount, 0, ',', '.') }}₫
                    @endif
                </dd>

                <dt class="col-5 col-md-3 fw-normal text-secondary">Áp dụng cho</dt>
                <dd class="col-7 col-md-9">
                    {{ $voucher->packages->isEmpty() ? 'Mọi gói' : $voucher->packages->pluck('name')->implode(', ') }}
                </dd>

                <dt class="col-5 col-md-3 fw-normal text-secondary">Hiệu lực</dt>
                <dd class="col-7 col-md-9">
                    {{ $voucher->starts_at?->format('d/m/Y H:i') ?? 'không giới hạn' }} →
                    {{ $voucher->ends_at?->format('d/m/Y H:i') ?? 'không giới hạn' }}
                </dd>

                <dt class="col-5 col-md-3 fw-normal text-secondary">Giới hạn lượt</dt>
                <dd class="col-7 col-md-9">
                    {{ $voucher->max_uses ? 'tổng ' . $voucher->max_uses : 'không giới hạn tổng' }} ·
                    {{ $voucher->max_uses_per_user }} lượt / người
                </dd>

                <dt class="col-5 col-md-3 fw-normal text-secondary">Người tạo</dt>
                <dd class="col-7 col-md-9">
                    {{ $voucher->creator?->name ?? '—' }} · {{ $voucher->created_at->format('d/m/Y') }}
                </dd>
            </dl>
        </div>
    </div>

    <h2 class="h6 fw-bold mb-2">Lượt dùng</h2>
    <div class="table-meta">{{ number_format($redemptions->total(), 0, ',', '.') }} lượt</div>

    <div class="table-responsive">
        <table class="table table-sm align-middle small">
            <thead>
                <tr>
                    <th>Người dùng</th><th>Đơn</th><th class="text-end">Giảm</th>
                    <th class="text-end">Thực trả</th><th>Trạng thái</th><th>Lúc</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($redemptions as $r)
                    <tr>
                        <td>
                            <div class="fw-semibold">{{ $r->user?->name ?? '—' }}</div>
                            <div class="text-secondary">{{ $r->user?->email }}</div>
                        </td>
                        <td class="font-monospace">
                            @if ($r->payment)
                                <a href="{{ route('admin.payments.show', $r->payment) }}"
                                   class="text-decoration-none">{{ $r->payment->order_code }}</a>
                            @else
                                —
                            @endif
                        </td>
                        <td class="text-end text-success">
                            −{{ number_format((float) $r->discount_amount, 0, ',', '.') }}₫
                        </td>
                        <td class="text-end">
                            {{ $r->payment ? number_format((float) $r->payment->amount, 0, ',', '.') . '₫' : '—' }}
                        </td>
                        <td>
                            {{-- Ba trạng thái: đang giữ chỗ · đã dùng thật · đã trả lại lượt. --}}
                            @if ($r->released_at)
                                <span class="badge text-bg-secondary">Đã trả lại</span>
                            @elseif ($r->redeemed_at)
                                <span class="badge text-bg-success">Đã dùng</span>
                            @else
                                <span class="badge text-bg-warning">Đang giữ chỗ</span>
                            @endif
                        </td>
                        <td class="text-nowrap">{{ $r->created_at->format('H:i d/m/Y') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="text-center text-secondary py-4">Chưa ai dùng mã này.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <x-pagination :paginator="$redemptions" label="lượt" />
@endsection
