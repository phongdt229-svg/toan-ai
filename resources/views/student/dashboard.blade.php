@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Trang chủ — TOÁN AI')
@section('page_title', 'Trang chủ')

@section('content')
    <div class="mb-4">
        <h2 class="h5 fw-bold mb-1">Chào {{ $user->name }} 👋</h2>
        <p class="text-secondary small mb-0">
            @if ($grade)
                Bạn đang học chương trình {{ $grade->name }}.
            @else
                Hãy cập nhật lớp học của bạn trong hồ sơ.
            @endif
        </p>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Bài học hoàn thành', $stats['lessons_completed'], 'bi-journal-check'],
            ['Bài tập đã làm', $stats['exercises_done'], 'bi-pencil-square'],
            ['Thời gian học', $stats['study_minutes'] . ' phút', 'bi-clock'],
            ['Điểm trung bình', $stats['average_score'] ?? '—', 'bi-star'],
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

    <div class="alert alert-light border">
        <div class="fw-semibold mb-1"><i class="bi bi-tools me-1"></i>Đang xây dựng</div>
        <p class="small text-secondary mb-0">
            Nội dung bài học, luyện tập và AI Tutor sẽ xuất hiện ở đây từ Phase 2 trở đi
            (xem <code>PROJECT_PLAN.md</code>).
        </p>
    </div>
@endsection
