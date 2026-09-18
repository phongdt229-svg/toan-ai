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

    {{-- Gói Free: mời nâng cấp ngay trên trang chủ, vì đây là nơi học sinh vào mỗi ngày. --}}
    @if (! $subscription && $upgradePackage)
        <div class="card border-warning mb-4">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <i class="bi bi-gem text-warning" style="font-size:2.25rem"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold">Em đang dùng gói Free</div>
                    <div class="text-secondary small">
                        Nâng cấp để luyện tập không giới hạn, mở toàn bộ bài học và hỏi AI nhiều hơn mỗi ngày —
                        từ {{ $upgradePackage->priceLabel() }}/{{ $upgradePackage->durationLabel() }}.
                    </div>
                </div>
                <a href="{{ route('packages.index') }}" class="btn btn-warning btn-touch">Xem gói học</a>
            </div>
        </div>
    @endif

    {{-- §36 Dashboard tiến độ: % theo lộ trình · buổi đã học / còn lại · điểm TB · kiến thức yếu --}}
    @if (! $path)
        <div class="card border-primary mb-4">
            <div class="card-body d-flex flex-wrap align-items-center gap-3">
                <i class="bi bi-compass text-primary" style="font-size:2.5rem"></i>
                <div class="flex-grow-1">
                    <div class="fw-bold">{{ $hasPlacement ? 'Em chưa có lộ trình học' : 'Làm kiểm tra đầu vào để nhận lộ trình riêng' }}</div>
                    <div class="text-secondary small">Khoảng 20 phút. Hệ thống sẽ biết em vững phần nào, yếu phần nào và xếp các buổi học cho em.</div>
                </div>
                <a href="{{ route('student.placement.intro') }}" class="btn btn-primary btn-touch">Bắt đầu</a>
            </div>
        </div>
    @else
        <div class="row g-3 mb-3">
            @foreach ([
                ['Hoàn thành lộ trình', $path->progress_percent . '%', 'bi-graph-up'],
                ['Buổi đã học', $path->completed_sessions, 'bi-check2-square'],
                ['Buổi còn lại', $path->remainingSessions(), 'bi-calendar3'],
                ['Điểm trung bình', $averageScore !== null ? \App\Support\Score::format($averageScore) . '/10' : '—', 'bi-star'],
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

        <div class="progress mb-3" style="height:8px" role="progressbar"
             aria-valuenow="{{ $path->progress_percent }}" aria-valuemin="0" aria-valuemax="100">
            <div class="progress-bar" style="width:{{ $path->progress_percent }}%"></div>
        </div>

        @if ($weakTopics->isNotEmpty())
            <div class="small mb-4">
                <span class="text-secondary"><i class="bi bi-exclamation-circle me-1"></i>Kiến thức cần củng cố:</span>
                @foreach ($weakTopics as $m)
                    <span class="badge text-bg-light border">{{ $m->topic->name }} · {{ $m->mastery_score }}%</span>
                @endforeach
            </div>
        @endif
    @endif

    <div class="row g-2 mb-4">
        @foreach ([
            ['Bài đang học', $stats['lessons_started'], 'bi-journal-text'],
            ['Bài hoàn thành', $stats['lessons_completed'], 'bi-journal-check'],
            ['Thời gian học', $stats['study_minutes'] . ' phút', 'bi-clock'],
        ] as [$label, $value, $icon])
            <div class="col-4">
                <div class="border rounded-3 px-2 py-1 small">
                    <div class="text-secondary"><i class="bi {{ $icon }} me-1"></i>{{ $label }}</div>
                    <div class="fw-semibold">{{ $value }}</div>
                </div>
            </div>
        @endforeach
    </div>

    {{-- Gợi ý học hôm nay: buổi học hiện tại của lộ trình, hoặc đề xuất §11 khi chưa có lộ trình --}}
    @if ($currentSession)
        <div class="card border-primary mb-4">
            <div class="card-body">
                <div class="d-flex align-items-center justify-content-between mb-1">
                    <h3 class="h6 fw-bold mb-0"><i class="bi bi-stars text-primary me-1"></i>Gợi ý học hôm nay · Buổi {{ $currentSession->session_no }}</h3>
                    <a href="{{ route('student.path.show') }}" class="small">Xem lộ trình</a>
                </div>
                @foreach ($currentSession->items as $item)
                    @include('student.path.partials.item', ['item' => $item, 'service' => $pathService])
                @endforeach
                @if ($currentSession->status === 'quiz_pending')
                    <a href="{{ route('student.path.quiz', $currentSession) }}" class="btn btn-success w-100 btn-touch mt-2">
                        <i class="bi bi-clipboard-check me-1"></i>Kiểm tra cuối buổi
                    </a>
                @endif
            </div>
        </div>
    @elseif ($recommendations->isNotEmpty())
        <h3 class="h6 fw-bold mb-2"><i class="bi bi-stars text-primary me-1"></i>Gợi ý học hôm nay</h3>
        <div class="mb-4">
            @include('components.recommendation-list', ['recommendations' => $recommendations, 'actionable' => true])
        </div>
    @endif

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
