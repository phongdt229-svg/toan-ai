@extends('layouts.app', ['portal' => 'parent'])

@section('title', 'Tổng quan — TOÁN AI')
@section('page_title', 'Tổng quan')

@section('content')
    <h2 class="h5 fw-bold mb-3">Con của tôi</h2>

    @if ($children->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4">
                <i class="bi bi-person-plus text-secondary" style="font-size:2.5rem"></i>
                <p class="mt-3 mb-1 fw-semibold">Chưa liên kết với con nào</p>
                <p class="text-secondary small mb-0">
                    Lấy mã liên kết 8 ký tự trong tài khoản của con để kết nối.
                    Chức năng liên kết trong portal sẽ có ở Phase 6.
                </p>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($children as $child)
                <div class="col-12 col-lg-6">
                    <div class="card border h-100">
                        <div class="card-body">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-person-circle fs-4 text-primary"></i>
                                <div>
                                    <div class="fw-semibold">{{ $child->name }}</div>
                                    <div class="text-secondary small">
                                        {{ $child->studentProfile?->grade?->name ?? 'Chưa chọn lớp' }}
                                    </div>
                                </div>
                            </div>

                            <div class="row g-2">
                                @foreach ([['Tiến độ', '—'], ['Điểm TB', '—'], ['Thời gian học', '—'], ['Bài hoàn thành', '—']] as [$label, $value])
                                    <div class="col-6">
                                        <div class="stat-card">
                                            <div class="stat-card__label">{{ $label }}</div>
                                            <div class="stat-card__value">{{ $value }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            <p class="text-secondary small mb-0 mt-3">
                                Số liệu thật sẽ hiển thị khi hệ thống tiến độ hoàn thiện (Phase 6).
                            </p>
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
