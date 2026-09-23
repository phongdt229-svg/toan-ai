@extends('layouts.app', ['portal' => 'student'])

@section('title', $exam->title . ' — TOÁN AI')
@section('page_title', 'Đề kiểm tra')

@php
    use App\Support\Score;

    $used = $attempts->count();
    $left = max(0, $exam->max_attempts - $used);
    $current = $attempts->firstWhere('status', 'in_progress');
@endphp

@section('content')
    <a href="{{ route('student.exams.index') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Danh sách đề
    </a>

    <div class="card border mt-2 mb-4">
        <div class="card-body p-4">
            <span class="badge text-bg-primary mb-2">{{ $exam->typeLabel() }}</span>
            <h1 class="h4 fw-bold mb-2">{{ $exam->title }}</h1>

            @if ($exam->description)
                <div class="text-secondary mb-3" data-math>{!! $exam->description !!}</div>
            @endif

            <div class="row g-2 mb-4">
                @foreach ([
                    ['bi-clock', 'Thời gian', $exam->duration_minutes . ' phút'],
                    ['bi-list-ol', 'Số câu', $exam->total_questions],
                    ['bi-star', 'Tổng điểm', Score::format($exam->total_points)],
                    ['bi-arrow-repeat', 'Lượt còn lại', $left . '/' . $exam->max_attempts],
                ] as [$icon, $label, $value])
                    <div class="col-6 col-md-3">
                        <div class="stat-card">
                            <div class="stat-card__label"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</div>
                            <div class="stat-card__value fs-5">{{ $value }}</div>
                        </div>
                    </div>
                @endforeach
            </div>

            @if ($isLocked)
                <div class="alert alert-warning mb-0">
                    <i class="bi bi-lock me-1"></i>
                    Đề này thuộc gói {{ strtoupper($exam->access_level) }}. Nâng cấp để làm bài.
                </div>
            @elseif (! $exam->isOpen())
                <div class="alert alert-secondary mb-0">Đề hiện không trong thời gian mở.</div>
            @elseif ($current)
                <a href="{{ route('student.exams.take', $current) }}" class="btn btn-warning btn-lg w-100 btn-touch">
                    <i class="bi bi-play-fill me-1"></i>Làm tiếp bài đang dở
                </a>
            @elseif ($left > 0)
                <div class="alert alert-light border small">
                    <i class="bi bi-info-circle me-1"></i>
                    Khi bấm bắt đầu, đồng hồ sẽ chạy và <strong>không dừng lại</strong> kể cả khi bạn đóng trang.
                    Hết giờ bài sẽ được nộp tự động.
                </div>

                <form method="POST" action="{{ route('student.exams.start', $exam) }}"
                      data-confirm="Bắt đầu làm bài? Đồng hồ sẽ chạy ngay." data-confirm-ok="Bắt đầu">
                    @csrf
                    <button class="btn btn-primary btn-lg w-100 btn-touch">
                        <i class="bi bi-play-fill me-1"></i>Bắt đầu làm bài
                    </button>
                </form>
            @else
                <div class="alert alert-secondary mb-0">Bạn đã dùng hết lượt làm đề này.</div>
            @endif
        </div>
    </div>

    @if ($attempts->where('status', '!=', 'in_progress')->isNotEmpty())
        <h2 class="h6 fw-bold mb-2">Các lượt đã làm</h2>

        <div class="d-grid gap-2">
            @foreach ($attempts->where('status', '!=', 'in_progress') as $attempt)
                <a href="{{ route('student.exams.result', $attempt) }}" class="card border text-decoration-none text-body">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <span class="badge text-bg-light border">Lượt {{ $attempt->attempt_no }}</span>

                        <div class="flex-grow-1">
                            <div class="fw-semibold">
                                {{ Score::format($attempt->score) }}/{{ Score::format($attempt->total_points) }} điểm
                                @if ($attempt->status === 'submitted')
                                    <span class="badge text-bg-secondary ms-1">Chờ chấm tự luận</span>
                                @endif
                            </div>
                            <div class="text-secondary small">
                                Nộp {{ $attempt->submitted_at?->format('H:i d/m/Y') }}
                                @if ($attempt->auto_submitted) · tự nộp khi hết giờ @endif
                            </div>
                        </div>

                        <i class="bi bi-chevron-right text-secondary"></i>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
