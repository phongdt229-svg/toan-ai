@extends('layouts.app', ['portal' => 'admin'])

@php use App\Models\Voucher; @endphp

@section('title', 'Mã giảm giá — Quản trị TOÁN AI')
@section('page_title', 'Mã giảm giá')

@section('content')
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
                                      onsubmit="return confirm('Xoá mã {{ $voucher->code }}?')">
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
