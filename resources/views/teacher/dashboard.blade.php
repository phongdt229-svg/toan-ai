@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Dashboard giáo viên — TOÁN AI')
@section('page_title', 'Dashboard')

@section('content')
    <div class="mb-4">
        <h2 class="h5 fw-bold mb-1">Chào {{ $user->name }}</h2>
        <p class="text-secondary small mb-0">{{ $user->teacherProfile?->school }}</p>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Số lớp', $stats['classes'], 'bi-people'],
            ['Số học sinh', $stats['students'], 'bi-mortarboard'],
            ['Bài đang giao', $stats['open_assignments'], 'bi-pencil-square'],
            ['Điểm trung bình', $stats['average_score'] ?? '—', 'bi-star'],
            ['Cần hỗ trợ', $stats['students_needing_help'], 'bi-exclamation-triangle'],
        ] as [$label, $value, $icon])
            <div class="col-6 col-lg-4 col-xl">
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

    <div class="alert alert-light border">
        <div class="fw-semibold mb-1"><i class="bi bi-tools me-1"></i>Đang xây dựng</div>
        <p class="small text-secondary mb-0">
            Lớp học, ngân hàng câu hỏi, đề kiểm tra và giao bài sẽ có từ Phase 3–5.
        </p>
    </div>
@endsection
