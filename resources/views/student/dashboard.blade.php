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
            ['Bài đang học', $stats['lessons_started'], 'bi-journal-text'],
            ['Bài hoàn thành', $stats['lessons_completed'], 'bi-journal-check'],
            ['Thời gian học', $stats['study_minutes'] . ' phút', 'bi-clock'],
        ] as [$label, $value, $icon])
            <div class="col-6 col-lg-4">
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

    @if ($pendingAssignments->isNotEmpty())
        <div class="d-flex align-items-center justify-content-between mb-2">
            <h3 class="h6 fw-bold mb-0">Bài được giao</h3>
            <a href="{{ route('student.assignments.index') }}" class="small">Xem tất cả</a>
        </div>
        <div class="d-grid gap-2 mb-4">
            @foreach ($pendingAssignments as $record)
                @include('student.assignments.partials.card', ['record' => $record])
            @endforeach
        </div>
    @endif

    @if ($continueLearning->isNotEmpty())
        <h3 class="h6 fw-bold mb-2">Học tiếp</h3>
        <div class="d-grid gap-2 mb-4">
            @foreach ($continueLearning as $item)
                <a href="{{ route('student.lesson.show', $item->lesson) }}"
                   class="card border text-decoration-none text-body">
                    <div class="card-body d-flex align-items-center gap-3 py-3">
                        <i class="bi bi-play-circle text-primary fs-4"></i>
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $item->lesson->title }}</div>
                            <div class="text-secondary small">{{ $item->lesson->topic->name }}</div>
                            <div class="progress mt-2" style="height:6px" role="progressbar"
                                 aria-valuenow="{{ $item->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
                                <div class="progress-bar" style="width:{{ $item->progress_percent }}%"></div>
                            </div>
                        </div>
                        <span class="badge text-bg-light border">{{ $item->progress_percent }}%</span>
                    </div>
                </a>
            @endforeach
        </div>
    @endif

    @if ($topicProgress)
        <h3 class="h6 fw-bold mb-2">Tiến độ theo chủ đề</h3>
        <div class="card border mb-4">
            <div class="card-body d-grid gap-3">
                @foreach ($topicProgress as $row)
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span>{{ $row['topic'] }}</span>
                            <span class="fw-semibold">{{ $row['percent'] }}%</span>
                        </div>
                        <div class="progress" style="height:8px" role="progressbar"
                             aria-valuenow="{{ $row['percent'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width:{{ $row['percent'] }}%"></div>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($stats['lessons_started'] === 0)
        <div class="card border">
            <div class="card-body text-center p-4">
                <i class="bi bi-rocket-takeoff text-primary" style="font-size:2.5rem"></i>
                <p class="mt-3 mb-3 fw-semibold">Bắt đầu bài học đầu tiên của bạn</p>
                <a href="{{ route('student.learn.index') }}" class="btn btn-primary btn-touch">Xem chương trình</a>
            </div>
        </div>
    @endif
@endsection
