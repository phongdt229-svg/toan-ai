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
            ['Số lớp', $stats['classes'], 'bi-people', route('teacher.classes.index')],
            ['Số học sinh', $stats['students'], 'bi-mortarboard', route('teacher.students.index')],
            ['Bài đang giao', $stats['open_assignments'], 'bi-send-check', route('teacher.assignments.index')],
            ['Điểm trung bình', $stats['average_score'] !== null ? $stats['average_score'] . '%' : '—', 'bi-star', null],
            ['Cần hỗ trợ', $stats['students_needing_help'], 'bi-exclamation-triangle', route('teacher.students.index', ['filter' => 'needs_support'])],
        ] as [$label, $value, $icon, $link])
            <div class="col-6 col-lg-4 col-xl">
                @if ($link)<a href="{{ $link }}" class="text-decoration-none text-body">@endif
                <div class="stat-card">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="stat-card__label">{{ $label }}</div>
                        <i class="bi {{ $icon }} text-primary"></i>
                    </div>
                    <div class="stat-card__value">{{ $value }}</div>
                </div>
                @if ($link)</a>@endif
            </div>
        @endforeach
    </div>

    @if ($stats['classes'] === 0)
        <div class="card border border-primary mb-4">
            <div class="card-body text-center p-4">
                <p class="fw-semibold mb-2">Bắt đầu bằng việc tạo lớp đầu tiên</p>
                <p class="text-secondary small">Học sinh tham gia bằng mã lớp, sau đó bạn có thể giao bài và theo dõi.</p>
                <a href="{{ route('teacher.classes.create') }}" class="btn btn-primary btn-touch">Tạo lớp</a>
            </div>
        </div>
    @endif

    @if ($pendingGrading > 0)
        <div class="alert alert-warning d-flex align-items-center gap-2">
            <i class="bi bi-pencil-square"></i>
            <span class="flex-grow-1">Có <strong>{{ $pendingGrading }}</strong> bài kiểm tra còn câu tự luận chờ chấm.</span>
            <a href="{{ route('teacher.exams.index') }}" class="btn btn-sm btn-warning">Xem</a>
        </div>
    @endif

    <div class="row g-4">
        <div class="col-12 col-lg-6">
            <h3 class="h6 fw-bold mb-2">Học sinh cần hỗ trợ</h3>
            @forelse ($needSupport as $row)
                <a href="{{ route('teacher.students.show', $row['student']) }}" class="card border mb-2 text-decoration-none text-body">
                    <div class="card-body py-2 d-flex align-items-center gap-2">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $row['student']->name }}</div>
                            <div class="text-secondary small">{{ implode(', ', $row['classes']) }}</div>
                        </div>
                        <div class="text-end small">
                            <div>{{ $row['avg_percent'] !== null ? $row['avg_percent'] . '%' : '—' }}</div>
                            @if ($row['overdue'] > 0)<div class="text-danger">{{ $row['overdue'] }} quá hạn</div>@endif
                        </div>
                    </div>
                </a>
            @empty
                <p class="text-secondary small">Không có học sinh nào cần hỗ trợ.</p>
            @endforelse
        </div>

        <div class="col-12 col-lg-6">
            <h3 class="h6 fw-bold mb-2">Sắp đến hạn (7 ngày)</h3>
            @forelse ($dueSoon as $a)
                <a href="{{ route('teacher.assignments.show', $a) }}" class="card border mb-2 text-decoration-none text-body">
                    <div class="card-body py-2 d-flex align-items-center gap-2">
                        <div class="flex-grow-1">
                            <div class="fw-semibold">{{ $a->title }}</div>
                            <div class="text-secondary small">{{ $a->schoolClass->name }} · hạn {{ $a->due_at->format('H:i d/m') }}</div>
                        </div>
                        <span class="badge text-bg-light border">{{ $a->done_count }}/{{ $a->recipients_count }}</span>
                    </div>
                </a>
            @empty
                <p class="text-secondary small">Không có bài nào sắp đến hạn.</p>
            @endforelse
        </div>
    </div>
@endsection
