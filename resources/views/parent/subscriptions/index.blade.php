@extends('layouts.app', ['portal' => 'parent'])

@section('title', 'Gói học của con — TOÁN AI')
@section('page_title', 'Gói học')

@section('content')
    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
        <h2 class="h5 fw-bold mb-0">Gói học của con</h2>
        <a href="{{ route('packages.index') }}" class="btn btn-sm btn-outline-primary ms-auto">Bảng giá</a>
    </div>

    @if ($children->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4">
                <p class="text-secondary mb-3">Liên kết với tài khoản của con để xem và mua gói học.</p>
                <a href="{{ route('parent.children.link') }}" class="btn btn-primary">Liên kết con</a>
            </div>
        </div>
    @else
        <div class="row g-3 mb-4">
            @foreach ($children as ['student' => $student, 'current' => $current])
                <div class="col-12 col-md-6">
                    <div class="card border h-100">
                        <div class="card-body">
                            <div class="fw-semibold">{{ $student->name }}</div>
                            <div class="small text-secondary mb-2">{{ $student->studentProfile?->grade?->name ?? 'Chưa chọn lớp' }}</div>

                            @if ($current)
                                <div class="mb-3">
                                    <span class="badge text-bg-success">{{ $current->package->name }}</span>
                                    <span class="small">còn {{ $current->daysLeft() }} ngày (đến {{ $current->ends_at->format('d/m/Y') }})</span>
                                </div>
                            @else
                                <div class="mb-3"><span class="badge text-bg-light border">Free</span></div>
                            @endif

                            <a href="{{ route('packages.index') }}" class="btn btn-sm {{ $current ? 'btn-outline-primary' : 'btn-warning' }}">
                                {{ $current ? 'Gia hạn / nâng cấp' : 'Mua gói cho con' }}
                            </a>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif

    @if ($purchases->isNotEmpty())
        <div class="d-flex align-items-center mb-2">
            <h2 class="h6 fw-bold mb-0">Gói bạn đã mua</h2>
            <a href="{{ route('payment.history') }}" class="small ms-auto">Lịch sử thanh toán</a>
        </div>
        <div class="table-responsive">
            <table class="table table-sm align-middle small">
                <thead><tr><th>Ngày</th><th>Cho</th><th>Gói</th><th>Trạng thái</th><th class="text-end">Giá</th></tr></thead>
                <tbody>
                    @foreach ($purchases as $sub)
                        <tr>
                            <td>{{ $sub->created_at->format('d/m/Y') }}</td>
                            <td>{{ $sub->user->name }}</td>
                            <td>{{ $sub->package->name }}</td>
                            <td>{{ $sub->statusLabel() }}</td>
                            <td class="text-end">{{ number_format((float) $sub->price_paid, 0, ',', '.') }}₫</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection
