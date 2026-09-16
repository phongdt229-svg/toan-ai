@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Quản trị — TOÁN AI')
@section('page_title', 'Dashboard')

@section('content')
    <div class="row g-3 mb-4">
        @foreach ([
            ['Tổng người dùng', $stats['users'], 'bi-people'],
            ['Học sinh', $stats['students'], 'bi-mortarboard'],
            ['Giáo viên', $stats['teachers'], 'bi-person-video3'],
            ['Phụ huynh', $stats['parents'], 'bi-house-heart'],
        ] as [$label, $value, $icon])
            <div class="col-6 col-lg-3">
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="stat-card__label">{{ $label }}</div>
                        <i class="bi {{ $icon }} text-primary"></i>
                    </div>
                    <div class="stat-card__value">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($stats['pending_teachers'] > 0)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-person-exclamation"></i>
            <span class="flex-grow-1">
                Có <strong>{{ $stats['pending_teachers'] }}</strong> tài khoản giáo viên đang chờ duyệt.
            </span>
            <a href="{{ route('admin.teachers.pending') }}" class="btn btn-sm btn-warning">Xem ngay</a>
        </div>
    @endif

    <div class="alert alert-light border">
        <div class="fw-semibold mb-1"><i class="bi bi-tools me-1"></i>Đang xây dựng</div>
        <p class="small text-secondary mb-0">
            Quản lý nội dung, gói học, giao dịch và audit log sẽ có ở các phase sau.
        </p>
    </div>
@endsection
