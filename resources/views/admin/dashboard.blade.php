@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Quản trị — TOÁN AI')
@section('page_title', 'Dashboard')

@push('head')
    @vite('resources/js/charts.js')
@endpush

@php
    $money = fn ($v) => number_format($v, 0, ',', '.') . '₫';

    // Học sinh không có gói trả phí hiệu lực → coi là Free, để donut thấy đủ 3 nhóm.
    $freeStudents = max(0, $a['users']['students'] - array_sum($a['subscriptions']));
    $subscriptionChart = [
        ['label' => \App\Models\Package::TIER_LABELS[\App\Models\Package::TIER_FREE], 'count' => $freeStudents, 'color' => '#cbd5e1'],
        ['label' => \App\Models\Package::TIER_LABELS[\App\Models\Package::TIER_PRO], 'count' => $a['subscriptions']['pro'], 'color' => '#3b82f6'],
        ['label' => \App\Models\Package::TIER_LABELS[\App\Models\Package::TIER_PREMIUM], 'count' => $a['subscriptions']['premium'], 'color' => '#a855f7'],
    ];

    $weakTopicsChart = collect($a['weak_topics'])
        ->map(fn ($t) => ['topic' => $t['topic'], 'percent' => $t['avg'], 'students' => $t['students']])
        ->all();
@endphp

@section('content')
    @if ($a['users']['pending_teachers'] > 0)
        <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-person-exclamation"></i>
            <span class="flex-grow-1">
                Có <strong>{{ $a['users']['pending_teachers'] }}</strong> tài khoản giáo viên đang chờ duyệt.
            </span>
            <a href="{{ route('admin.teachers.pending') }}" class="btn btn-sm btn-warning">Xem ngay</a>
        </div>
    @endif

    <div class="row g-3 mb-3">
        @foreach ([
            ['Doanh thu tháng này', $money($a['revenue']['month']), 'bi-cash-coin', $a['revenue']['paid_orders_month'] . ' đơn thành công'],
            ['Học sinh hoạt động 7 ngày', $a['engagement']['active_7d'], 'bi-activity', $a['engagement']['answers_7d'] . ' câu đã làm'],
            ['Đang dùng gói trả phí', array_sum($a['subscriptions']), 'bi-gem', 'Pro ' . $a['subscriptions']['pro'] . ' · Premium ' . $a['subscriptions']['premium']],
            ['Người dùng mới 30 ngày', $a['users']['new_30d'], 'bi-person-plus', 'Tổng ' . $a['users']['total']],
        ] as [$label, $value, $icon, $hint])
            <div class="col-6 col-lg-3">
                <div class="stat-card h-100">
                    <div class="d-flex justify-content-between align-items-start">
                        <div class="stat-card__label">{{ $label }}</div>
                        <i class="bi {{ $icon }} text-primary"></i>
                    </div>
                    <div class="stat-card__value">{{ $value }}</div>
                    <div class="small text-secondary">{{ $hint }}</div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="row g-3 mb-3">
        <div class="col-12 col-xl-8">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center mb-2">
                        <div class="fw-semibold">30 ngày qua</div>
                        <form method="POST" action="{{ route('admin.dashboard.refresh') }}" class="ms-auto">
                            @csrf
                            <button class="btn btn-sm btn-link text-decoration-none p-0" title="Số liệu được lưu tạm 10 phút">
                                <i class="bi bi-arrow-clockwise me-1"></i>Làm mới
                            </button>
                        </form>
                    </div>
                    <div class="position-relative">
                        <canvas data-chart-type="admin-daily" data-chart='@json($a['daily'])'
                                role="img" aria-label="Biểu đồ học sinh hoạt động, người dùng mới và doanh thu theo ngày"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border h-100">
                <div class="card-body small d-grid gap-2 align-content-start">
                    <div class="fw-semibold fs-6">Tổng quan</div>
                    @foreach ([
                        ['Học sinh', $a['users']['students']],
                        ['Giáo viên', $a['users']['teachers']],
                        ['Phụ huynh', $a['users']['parents']],
                        ['Học sinh hoạt động 30 ngày', $a['engagement']['active_30d']],
                        ['Doanh thu 30 ngày', $money($a['revenue']['last_30d'])],
                        ['Chi phí AI tháng này (ước tính)', '$' . number_format($a['ai_cost_month'], 2)],
                        ['Bài học đã xuất bản', $a['content']['lessons']],
                        ['Câu hỏi đã xuất bản', $a['content']['questions']],
                        ['Đề kiểm tra đã xuất bản', $a['content']['exams']],
                    ] as [$label, $value])
                        <div class="d-flex justify-content-between border-bottom pb-1">
                            <span class="text-secondary">{{ $label }}</span><strong>{{ $value }}</strong>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-12 col-xl-8">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-1">Chủ đề học sinh yếu nhất</div>
                    <p class="small text-secondary">Điểm thành thạo trung bình thấp nhất (chủ đề có từ 3 học sinh) — nên bổ sung bài giảng, câu hỏi.</p>
                    @if ($weakTopicsChart)
                        <canvas data-chart-type="topic-bars" data-chart='@json($weakTopicsChart)'
                                role="img" aria-label="Biểu đồ điểm thành thạo trung bình theo chủ đề, thấp nhất trước"></canvas>
                    @else
                        <p class="small text-secondary mb-0">Chưa đủ dữ liệu.</p>
                    @endif
                </div>
            </div>
        </div>

        <div class="col-12 col-xl-4">
            <div class="card border h-100">
                <div class="card-body">
                    <div class="fw-semibold mb-1">Học sinh theo gói</div>
                    <p class="small text-secondary">Free {{ $freeStudents }} · Pro {{ $a['subscriptions']['pro'] }} · Premium {{ $a['subscriptions']['premium'] }}</p>
                    <canvas data-chart-type="subscription-donut" data-chart='@json($subscriptionChart)'
                            role="img" aria-label="Biểu đồ phân bố học sinh theo gói Free, Pro, Premium"></canvas>
                </div>
            </div>
        </div>
    </div>

    <div class="small text-secondary mt-2">Số liệu lúc {{ \Illuminate\Support\Carbon::parse($a['generated_at'])->format('H:i d/m/Y') }}.</div>
@endsection
