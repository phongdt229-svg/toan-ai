@extends('layouts.app', ['portal' => 'parent'])

@section('title', $student->name . ' — TOÁN AI')
@section('page_title', $student->name)

@push('head')
    @vite('resources/js/charts.js')
@endpush

@php
    use App\Support\Duration;
    use App\Support\Score;

    $r = $report;
@endphp

@section('content')
    <a href="{{ route('parent.dashboard') }}" class="small text-decoration-none">
        <i class="bi bi-chevron-left"></i> Con của tôi
    </a>

    <div class="mt-2 mb-3">
        <h2 class="h4 fw-bold mb-0">{{ $student->name }}</h2>
        <div class="text-secondary small">
            {{ $r['grade']?->name ?? 'Chưa chọn lớp' }} ·
            gói <span class="badge text-bg-{{ $tier === 'free' ? 'light border' : 'warning text-dark' }}">{{ \App\Models\Package::TIER_LABELS[$tier] }}</span>
        </div>
    </div>

    {{-- §14: Tiến độ · Điểm trung bình · Thời gian học · Bài hoàn thành --}}
    <div class="row g-3 mb-4">
        @foreach ([
            ['Tiến độ', $r['curriculum_percent'] !== null ? $r['curriculum_percent'] . '%' : '—', 'bi-graph-up', 'Bài học đã hoàn thành trên tổng số bài của ' . ($r['grade']?->name ?? 'lớp')],
            ['Điểm trung bình', $r['average_score'] !== null ? Score::format($r['average_score']) : '—', 'bi-star', 'Thang 10, tính từ đề kiểm tra và bài tập được giao'],
            ['Thời gian học', Duration::human($r['study_seconds']), 'bi-clock', 'Thời gian đọc bài và làm bài có tương tác'],
            ['Bài hoàn thành', $r['lessons_completed'], 'bi-journal-check', 'Số bài học đã học xong'],
        ] as [$label, $value, $icon, $hint])
            <div class="col-6 col-lg-3">
                <div class="stat-card" title="{{ $hint }}">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="stat-card__label">{{ $label }}</div>
                        <i class="bi {{ $icon }} text-primary"></i>
                    </div>
                    <div class="stat-card__value">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    @if ($r['pending_assignments']['overdue'] > 0)
        <div class="alert alert-warning">
            <i class="bi bi-exclamation-triangle me-1"></i>
            Con có <strong>{{ $r['pending_assignments']['overdue'] }} bài quá hạn chưa nộp</strong>
            (tổng {{ $r['pending_assignments']['pending'] }} bài giáo viên giao đang chờ làm).
        </div>
    @elseif ($r['pending_assignments']['pending'] > 0)
        <div class="alert alert-light border small">
            Con có {{ $r['pending_assignments']['pending'] }} bài giáo viên giao đang chờ làm, chưa bài nào quá hạn.
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-7">
            {{-- Chủ đề mạnh / yếu --}}
            <div class="row g-3 mb-4">
                <div class="col-12 col-sm-6">
                    <div class="card border-success h-100">
                        <div class="card-body">
                            <div class="fw-semibold text-success mb-2"><i class="bi bi-hand-thumbs-up me-1"></i>Chủ đề mạnh</div>
                            @forelse ($r['strong_topics'] as $m)
                                <div class="d-flex justify-content-between small mb-1"><span>{{ $m->topic->name }}</span><span class="fw-semibold">{{ $m->mastery_score }}%</span></div>
                            @empty
                                <div class="small text-secondary">Chưa đủ bài làm để đánh giá.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
                <div class="col-12 col-sm-6">
                    <div class="card border-danger h-100">
                        <div class="card-body">
                            <div class="fw-semibold text-danger mb-2"><i class="bi bi-exclamation-circle me-1"></i>Chủ đề yếu</div>
                            @forelse ($r['weak_topics'] as $m)
                                <div class="d-flex justify-content-between small mb-1"><span>{{ $m->topic->name }}</span><span class="fw-semibold">{{ $m->mastery_score }}%</span></div>
                            @empty
                                <div class="small text-secondary">Chưa phát hiện chủ đề yếu.</div>
                            @endforelse
                        </div>
                    </div>
                </div>
            </div>

            {{-- Lộ trình cá nhân hóa (§35–36) --}}
            <div class="card border mb-4">
                <div class="card-body">
                    <div class="fw-semibold mb-2"><i class="bi bi-signpost-split text-primary me-1"></i>Lộ trình học</div>
                    @if ($r['path'])
                        <div class="d-flex justify-content-between small mb-1">
                            <span>Đã học {{ $r['path']->completed_sessions }}/{{ $r['path']->total_sessions }} buổi</span>
                            <span class="fw-semibold">{{ $r['path']->progress_percent }}%</span>
                        </div>
                        <div class="progress mb-3" style="height:8px" role="progressbar"
                             aria-valuenow="{{ $r['path']->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width:{{ $r['path']->progress_percent }}%"></div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach ($r['path']->stages as $stage)
                                <span class="badge text-bg-{{ ['done' => 'success', 'in_progress' => 'primary', 'locked' => 'light border'][$stage->status] }}">
                                    {{ $stage->sort_order }}. {{ $stage->name }} · {{ $stage->progress_percent }}%
                                </span>
                            @endforeach
                        </div>
                        @if ($r['path']->placementTest)
                            <div class="small text-secondary mt-2">
                                Kiểm tra đầu vào: {{ \App\Support\Score::format($r['path']->placementTest->score) }}/10 · {{ $r['path']->placementTest->levelLabel() }}
                            </div>
                        @endif
                    @else
                        <p class="text-secondary small mb-0">Con chưa làm kiểm tra đầu vào nên chưa có lộ trình riêng.</p>
                    @endif
                </div>
            </div>

            @if ($advancedReports)
                {{-- Đề xuất học tập (§11) --}}
                @if ($r['recommendations']->isNotEmpty())
                    <div class="fw-semibold mb-1"><i class="bi bi-stars text-primary me-1"></i>Đề xuất cho con</div>
                    <p class="text-secondary small">Tính từ kết quả làm bài thật của con, cập nhật sau mỗi lần con nộp bài.</p>
                    <div class="mb-4">
                        @include('components.recommendation-list', ['recommendations' => $r['recommendations'], 'actionable' => false])
                    </div>
                @endif

                {{-- Hoạt động 7 ngày --}}
                <div class="card border mb-4">
                    <div class="card-body">
                        <div class="fw-semibold mb-3">Số câu đã làm 7 ngày qua</div>
                        @if (collect($r['activity'])->sum('answered') === 0)
                            <p class="text-secondary small mb-0">Con chưa làm câu hỏi nào trong 7 ngày qua.</p>
                        @else
                            <div class="position-relative">
                                <canvas data-chart-type="daily-activity" data-chart='@json($r['activity'])'
                                        role="img" aria-label="Biểu đồ số câu đã làm mỗi ngày"></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            @else
                {{-- Báo cáo nâng cao thuộc gói Premium (§18). --}}
                <div class="card border-warning mb-4">
                    <div class="card-body d-flex flex-wrap align-items-center gap-3">
                        <i class="bi bi-graph-up-arrow text-warning fs-2"></i>
                        <div class="flex-grow-1">
                            <div class="fw-semibold">Báo cáo nâng cao</div>
                            <div class="small text-secondary">Biểu đồ học tập từng ngày và đề xuất học riêng cho con có trong gói Premium.</div>
                        </div>
                        <a href="{{ route('packages.index') }}" class="btn btn-sm btn-warning">Xem gói học</a>
                    </div>
                </div>
            @endif

            {{-- Đề kiểm tra gần đây --}}
            <div class="fw-semibold mb-2">Đề kiểm tra gần đây</div>
            @forelse ($r['recent_exams'] as $attempt)
                <div class="card border mb-2">
                    <div class="card-body py-2 d-flex align-items-center gap-2">
                        <div class="flex-grow-1 min-w-0">
                            <div class="fw-semibold text-truncate">{{ $attempt->exam?->title }}</div>
                            <div class="text-secondary small">{{ $attempt->submitted_at?->format('H:i d/m/Y') }}</div>
                        </div>
                        @if ($attempt->status === 'submitted')
                            <span class="badge text-bg-info">Chờ chấm</span>
                        @else
                            <span class="badge text-bg-{{ ($attempt->percent() ?? 0) >= 50 ? 'success' : 'danger' }}">
                                {{ Score::format($attempt->score) }}/{{ Score::format($attempt->total_points) }}
                            </span>
                        @endif
                    </div>
                </div>
            @empty
                <p class="text-secondary small">Con chưa làm đề kiểm tra nào.</p>
            @endforelse
        </div>

        <div class="col-12 col-lg-5">
            {{-- Nhận xét giáo viên --}}
            <div class="fw-semibold mb-2"><i class="bi bi-chat-quote me-1"></i>Nhận xét của giáo viên</div>
            @forelse ($r['comments'] as $comment)
                <div class="border-start border-3 border-primary ps-3 mb-3">
                    <div style="white-space:pre-line">{{ $comment->content }}</div>
                    <div class="text-secondary small mt-1">{{ $comment->teacher->name }} · {{ $comment->created_at->format('d/m/Y') }}</div>
                </div>
            @empty
                <p class="text-secondary small">Chưa có nhận xét nào.</p>
            @endforelse

            <hr class="my-4">

            <form method="POST" action="{{ route('parent.children.unlink', $student) }}"
                  onsubmit="return confirm('Huỷ liên kết với {{ $student->name }}? Bạn sẽ không xem được kết quả học của con nữa.')">
                @csrf
                @method('DELETE')
                <button class="btn btn-sm btn-outline-danger">Huỷ liên kết</button>
            </form>
        </div>
    </div>
@endsection
