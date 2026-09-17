@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Đề kiểm tra — TOÁN AI')
@section('page_title', 'Đề kiểm tra')

@section('content')
    <h2 class="h5 fw-bold mb-3">Đề kiểm tra đang mở</h2>

    @if ($exams->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-clipboard-check fs-2 d-block mb-2"></i>
                Hiện chưa có đề kiểm tra nào đang mở cho lớp của bạn.
            </div>
        </div>
    @else
        <div class="d-grid gap-2">
            @foreach ($exams as $exam)
                @php
                    $used = $exam->my_attempts_count;
                    $left = max(0, $exam->max_attempts - $used);
                    $doing = $inProgress->has($exam->id);
                @endphp

                <a href="{{ route('student.exams.show', $exam) }}" class="card border text-decoration-none text-body">
                    <div class="card-body">
                        <div class="d-flex align-items-start gap-3">
                            <div class="feature-card__icon mb-0 flex-shrink-0">
                                <i class="bi bi-clipboard-check"></i>
                            </div>

                            <div class="flex-grow-1 min-w-0">
                                <div class="d-flex flex-wrap align-items-center gap-2">
                                    <span class="fw-semibold">{{ $exam->title }}</span>
                                    @if ($doing)
                                        <span class="badge text-bg-warning text-dark">Đang làm dở</span>
                                    @endif
                                </div>

                                <div class="d-flex flex-wrap gap-2 mt-1">
                                    <span class="badge text-bg-light border">
                                        <i class="bi bi-clock me-1"></i>{{ $exam->duration_minutes }} phút
                                    </span>
                                    <span class="badge text-bg-light border">{{ $exam->total_questions }} câu</span>
                                    <span class="badge text-bg-light border">{{ $exam->difficultyLabel() }}</span>
                                </div>

                                <div class="text-secondary small mt-2">
                                    @if ($exam->my_best_score !== null)
                                        Điểm cao nhất: <strong>{{ \App\Support\Score::format($exam->my_best_score) }}</strong>/{{ \App\Support\Score::format($exam->total_points) }} ·
                                    @endif
                                    Còn {{ $left }}/{{ $exam->max_attempts }} lượt
                                    @if ($exam->available_to)
                                        · Đóng lúc {{ $exam->available_to->format('H:i d/m') }}
                                    @endif
                                </div>
                            </div>

                            <i class="bi bi-chevron-right text-secondary"></i>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
