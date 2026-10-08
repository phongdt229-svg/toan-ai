@extends('layouts.app', ['portal' => 'student'])

@section('title', 'Lộ trình học — TOÁN AI')
@section('page_title', 'Lộ trình học')

@php
    $stageTone = ['done' => 'success', 'in_progress' => 'primary', 'locked' => 'secondary'];
    $stageLabel = ['done' => 'Hoàn thành', 'in_progress' => 'Đang học', 'locked' => 'Chưa tới'];
@endphp

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <div>
            <h2 class="h5 fw-bold mb-0">Lộ trình của em</h2>
            <div class="text-secondary small">
                Tạo {{ $path->generated_at->format('d/m/Y') }}
                @if ($path->placementTest) từ kết quả kiểm tra đầu vào ({{ $path->placementTest->levelLabel() }}) @endif
            </div>
        </div>
        <a href="{{ route('student.placement.intro') }}" class="btn btn-sm btn-outline-secondary">Làm lại kiểm tra đầu vào</a>
    </div>

    <div class="row g-3 mb-4">
        @foreach ([
            ['Hoàn thành', $path->progress_percent . '%', 'bi-graph-up'],
            ['Buổi đã học', $path->completed_sessions, 'bi-check2-square'],
            ['Buổi còn lại', $path->remainingSessions(), 'bi-calendar3'],
        ] as [$label, $value, $icon])
            <div class="col-4">
                <div class="stat-card">
                    <div class="stat-card__label"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</div>
                    <div class="stat-card__value">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="progress mb-4" style="height:10px" role="progressbar"
         aria-valuenow="{{ $path->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar" style="width:{{ $path->progress_percent }}%"></div>
    </div>

    {{-- TA-06: mục tiêu + ngày dự kiến xong theo lịch học tuần. --}}
    @if ($estimatedFinish || $targetScore !== null)
        <p class="small text-secondary mb-4" data-testid="path-goal">
            @if ($estimatedFinish)
                <i class="bi bi-flag me-1"></i>Học đều theo lịch, em xong lộ trình khoảng <strong class="text-body">{{ $estimatedFinish->format('d/m/Y') }}</strong>.
            @endif
            @if ($targetScore !== null)
                <i class="bi bi-bullseye ms-1 me-1"></i>Mục tiêu: <strong class="text-body">{{ \App\Support\Score::format((float) $targetScore) }}</strong>
                @if ($path->placementTest)
                    (đầu vào {{ \App\Support\Score::format((float) $path->placementTest->score) }})
                @endif
            @endif
        </p>
    @elseif ($path->status !== 'completed')
        <p class="small text-secondary mb-4">
            <a href="{{ route('student.schedule.edit') }}">Đặt lịch học</a> để biết khi nào xong lộ trình, và
            <a href="{{ route('student.settings') }}">đặt mục tiêu điểm</a> trong Cài đặt.
        </p>
    @endif

    {{-- Buổi học hiện tại --}}
    @if ($path->status === 'completed')
        <div class="alert alert-success"><i class="bi bi-trophy me-1"></i>Em đã hoàn thành toàn bộ lộ trình. Tuyệt vời!</div>
    @elseif ($current && $currentLocked)
        @include('student.path.partials.locked', ['session' => $current, 'limit' => $sessionLimit])
    @elseif ($current)
        <div class="card border-primary mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-2">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-star-fill text-warning me-1"></i>Buổi {{ $current->session_no }} — học hôm nay</h3>
                    <span class="badge text-bg-light border">{{ $current->items->filter->isDone()->count() }}/{{ $current->items->count() }}</span>
                </div>

                @foreach ($current->items as $item)
                    @include('student.path.partials.item', ['item' => $item])
                @endforeach

                @if ($current->status === 'quiz_pending')
                    <a href="{{ route('student.path.quiz', $current) }}" class="btn btn-success w-100 btn-touch mt-2">
                        <i class="bi bi-clipboard-check me-1"></i>Kiểm tra cuối buổi (vài câu ngắn)
                    </a>
                @endif
            </div>
        </div>
    @elseif ($path->stages->flatMap->items->isEmpty())
        <div class="alert alert-light border">Chương trình lớp của em chưa có nội dung để xếp lộ trình. Em quay lại sau nhé.</div>
    @endif

    {{-- 4 giai đoạn §35 --}}
    <h3 class="h6 fw-bold mb-2">Các giai đoạn</h3>
    <div class="accordion" id="stages">
        @foreach ($path->stages as $stage)
            @php $id = 'stage-' . $stage->id; @endphp
            <div class="accordion-item">
                <h4 class="accordion-header">
                    <button class="accordion-button {{ $stage->status === 'in_progress' ? '' : 'collapsed' }}" type="button"
                            data-bs-toggle="collapse" data-bs-target="#{{ $id }}">
                        <span class="badge text-bg-{{ $stageTone[$stage->status] }} me-2">{{ $stage->sort_order }}</span>
                        <span class="flex-grow-1 fw-semibold">{{ $stage->name }}</span>
                        <span class="small text-secondary me-2">{{ $stageLabel[$stage->status] }} · {{ $stage->progress_percent }}%</span>
                    </button>
                </h4>
                <div id="{{ $id }}" class="accordion-collapse collapse {{ $stage->status === 'in_progress' ? 'show' : '' }}" data-bs-parent="#stages">
                    <div class="accordion-body py-2">
                        @forelse ($stage->items as $item)
                            @include('student.path.partials.item', ['item' => $item])
                        @empty
                            <p class="text-secondary small mb-0">Giai đoạn này không có mục nào.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endsection
