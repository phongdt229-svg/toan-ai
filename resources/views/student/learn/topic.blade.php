@extends('layouts.app', ['portal' => 'student'])

@section('title', $topic->name . ' — TOÁN AI')
@section('page_title', $topic->name)

@section('content')
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb small">
            <li class="breadcrumb-item"><a href="{{ route('student.learn.index') }}">Chương trình</a></li>
            <li class="breadcrumb-item">{{ $topic->chapter->subject->grade->name }}</li>
            <li class="breadcrumb-item">{{ $topic->chapter->name }}</li>
            <li class="breadcrumb-item active" aria-current="page">{{ $topic->name }}</li>
        </ol>
    </nav>

    <h2 class="h5 fw-bold mb-3">{{ $topic->name }}</h2>

    @if ($lessons->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4 text-secondary">
                <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                Chủ đề này chưa có bài học nào được xuất bản.
            </div>
        </div>
    @else
        <div class="d-grid gap-2">
            @foreach ($lessons as $lesson)
                @php $progress = $lesson->user_progress; @endphp

                <a href="{{ route('student.lesson.show', $lesson) }}"
                   class="card border text-decoration-none text-body">
                    <div class="card-body d-flex align-items-center gap-3">
                        <div class="feature-card__icon mb-0 flex-shrink-0">
                            @if ($lesson->is_locked)
                                <i class="bi bi-lock"></i>
                            @elseif ($progress?->isCompleted())
                                <i class="bi bi-check-circle text-success"></i>
                            @else
                                <i class="bi bi-play-circle"></i>
                            @endif
                        </div>

                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold">{{ $lesson->title }}</div>

                            @if ($lesson->summary)
                                <div class="text-secondary small text-truncate">{{ $lesson->summary }}</div>
                            @endif

                            <div class="d-flex flex-wrap gap-2 mt-1">
                                <span class="badge text-bg-light border">
                                    <i class="bi bi-clock me-1"></i>{{ $lesson->estimated_minutes }} phút
                                </span>
                                <span class="badge text-bg-light border">
                                    {{ ['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'][$lesson->difficulty] }}
                                </span>
                                @if ($lesson->access_level !== 'free')
                                    <span class="badge text-bg-warning text-dark">
                                        {{ strtoupper($lesson->access_level) }}
                                    </span>
                                @endif
                            </div>

                            @if ($progress && ! $lesson->is_locked && $progress->progress_percent > 0)
                                <div class="progress mt-2" style="height:6px" role="progressbar"
                                     aria-valuenow="{{ $progress->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
                                    <div class="progress-bar" style="width:{{ $progress->progress_percent }}%"></div>
                                </div>
                            @endif
                        </div>

                        <i class="bi bi-chevron-right text-secondary flex-shrink-0"></i>
                    </div>
                </a>
            @endforeach
        </div>
    @endif
@endsection
