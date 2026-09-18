@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Báo cáo lớp — TOÁN AI')
@section('page_title', 'Báo cáo')

@push('head')
    @vite('resources/js/charts.js')
@endpush

@section('content')
    @if ($classes->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4">
                <p class="text-secondary mb-3">Bạn chưa có lớp nào để xem báo cáo.</p>
                <a href="{{ route('teacher.classes.create') }}" class="btn btn-primary">Tạo lớp</a>
            </div>
        </div>
    @else
        <form method="GET" class="filter-bar">
            <label for="lop" class="fw-semibold">Lớp</label>
            <select id="lop" name="lop" class="form-select" style="max-width:280px" onchange="this.form.submit()">
                @foreach ($classes as $c)
                    <option value="{{ $c->id }}" @selected($class->id === $c->id)>
                        {{ $c->name }}{{ $c->status === 'archived' ? ' (đã lưu trữ)' : '' }}
                    </option>
                @endforeach
            </select>
            <noscript><button class="btn btn-outline-primary">Xem</button></noscript>
            <a href="{{ route('teacher.reports.export', $class) }}" class="btn btn-outline-secondary ms-auto">
                <i class="bi bi-download me-1"></i>Xuất bảng điểm CSV
            </a>
        </form>

        @php $s = $report['summary']; @endphp
        <div class="row g-3 mb-3">
            @foreach ([
                ['Học sinh', $s['students'], 'bi-people'],
                ['Tỉ lệ nộp bài', $s['completion'] !== null ? $s['completion'] . '%' : '—', 'bi-check2-square'],
                ['Điểm trung bình', $s['average'] !== null ? $s['average'] . '%' : '—', 'bi-graph-up'],
                ['Cần hỗ trợ', $s['needs_support'], 'bi-life-preserver'],
            ] as [$label, $value, $icon])
                <div class="col-6 col-lg-3">
                    <div class="stat-card h-100">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="stat-card__label">{{ $label }}</div>
                            <i class="bi {{ $icon }} text-primary"></i>
                        </div>
                        <div class="stat-card__value">{{ $value }}</div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-xl-6">
                <div class="card border h-100">
                    <div class="card-body">
                        <div class="fw-semibold mb-1">Chủ đề cả lớp còn yếu</div>
                        <p class="small text-secondary">Mức thành thạo trung bình, chỉ tính học sinh đã làm đủ số câu.</p>
                        @if (empty($report['topics']))
                            <p class="small text-secondary mb-0">Chưa đủ dữ liệu luyện tập.</p>
                        @else
                            <div class="position-relative">
                                <canvas data-chart-type="topic-bars" data-chart='@json($report['topics'])'
                                        role="img" aria-label="Biểu đồ mức thành thạo theo chủ đề của lớp"></canvas>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card border h-100">
                    <div class="card-body">
                        <div class="fw-semibold mb-2">Bài đã giao</div>
                        @forelse ($report['assignments'] as $a)
                            @php $pct = $a->recipients_count ? (int) round($a->done_count / $a->recipients_count * 100) : 0; @endphp
                            <div class="border-top py-2 small">
                                <div class="d-flex gap-2">
                                    <a href="{{ route('teacher.assignments.show', $a) }}" class="fw-semibold text-decoration-none flex-grow-1">{{ $a->title }}</a>
                                    <span class="text-secondary text-nowrap">{{ $a->due_at?->format('d/m') ?? 'không hạn' }}</span>
                                </div>
                                <div class="d-flex align-items-center gap-2 mt-1">
                                    <div class="progress flex-grow-1" style="height:6px">
                                        <div class="progress-bar" style="width: {{ $pct }}%"></div>
                                    </div>
                                    <span class="text-nowrap">{{ $a->done_count }}/{{ $a->recipients_count }} nộp</span>
                                    <span class="text-nowrap" style="width:4.5rem;text-align:right">TB {{ $a->avg_percent !== null ? (int) round($a->avg_percent) . '%' : '—' }}</span>
                                </div>
                            </div>
                        @empty
                            <p class="small text-secondary mb-0">Lớp chưa có bài giao.</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>

        <h2 class="h6 fw-bold mb-2">Học sinh <span class="fw-normal text-secondary small">— chưa làm bài và điểm thấp xếp trước</span></h2>
        @include('teacher.partials.student-table', ['students' => $report['students']])
    @endif
@endsection
