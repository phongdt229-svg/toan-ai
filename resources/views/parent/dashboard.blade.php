@extends('layouts.app', ['portal' => 'parent'])

@section('title', 'Con của tôi — TOÁN AI')
@section('page_title', 'Con của tôi')

@php
    use App\Support\Duration;
    use App\Support\Score;
@endphp

@section('content')
    <div class="d-flex flex-wrap gap-2 align-items-center justify-content-between mb-3">
        <h2 class="h5 fw-bold mb-0">Con của tôi</h2>
        @if ($children->isNotEmpty())
            <a href="{{ route('parent.children.link') }}" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-person-plus me-1"></i>Liên kết thêm
            </a>
        @endif
    </div>

    @if ($children->isEmpty())
        <div class="card border">
            <div class="card-body text-center p-4">
                <i class="bi bi-person-plus text-primary" style="font-size:2.5rem"></i>
                <p class="mt-3 mb-1 fw-semibold">Chưa liên kết với con nào</p>
                <p class="text-secondary small mb-3">
                    Nhờ con mở mục <strong>Phụ huynh</strong> trong tài khoản học sinh để lấy mã, link hoặc mã QR.
                </p>
                <a href="{{ route('parent.children.link') }}" class="btn btn-primary btn-touch">Nhập mã liên kết</a>
            </div>
        </div>
    @else
        <div class="row g-3">
            @foreach ($children as $row)
                @php $child = $row['student']; @endphp
                <div class="col-12 col-lg-6">
                    {{-- Thẻ không bọc trong <a> nữa: phần dưới có nút mua gói, không lồng link trong link được. --}}
                    <div class="card border h-100">
                        <a href="{{ route('parent.children.show', $child) }}" class="card-body d-block text-decoration-none text-body">
                            <div class="d-flex align-items-center gap-2 mb-3">
                                <i class="bi bi-person-circle fs-3 text-primary"></i>
                                <div class="flex-grow-1">
                                    <div class="fw-bold">{{ $child->name }}</div>
                                    <div class="text-secondary small">{{ $child->studentProfile?->grade?->name ?? 'Chưa chọn lớp' }}</div>
                                </div>
                                <i class="bi bi-chevron-right text-secondary"></i>
                            </div>

                            <div class="row g-2">
                                @foreach ([
                                    ['Tiến độ', $row['curriculum_percent'] !== null ? $row['curriculum_percent'] . '%' : '—'],
                                    ['Điểm TB', $row['average_score'] !== null ? Score::format($row['average_score']) : '—'],
                                    ['Thời gian học', Duration::human($row['study_seconds'])],
                                    ['Bài chưa làm', $row['assignments']['pending']],
                                ] as [$label, $value])
                                    <div class="col-6">
                                        <div class="stat-card">
                                            <div class="stat-card__label">{{ $label }}</div>
                                            <div class="stat-card__value fs-5">{{ $value }}</div>
                                        </div>
                                    </div>
                                @endforeach
                            </div>

                            @if ($row['assignments']['overdue'] > 0)
                                <div class="alert alert-warning small mt-3 mb-0 py-2">
                                    <i class="bi bi-exclamation-triangle me-1"></i>
                                    {{ $row['assignments']['overdue'] }} bài quá hạn chưa nộp
                                </div>
                            @endif
                        </a>

                        <div class="card-body border-top py-2 d-flex flex-wrap align-items-center gap-2">
                            @if ($row['subscription'])
                                <span class="badge text-bg-success">{{ $row['subscription']->package->name }}</span>
                                <span class="small text-secondary">còn {{ $row['subscription']->daysLeft() }} ngày</span>
                                <a href="{{ route('packages.checkout', ['package' => $row['subscription']->package, 'con' => $child->id]) }}"
                                   class="btn btn-sm btn-outline-primary ms-auto">Gia hạn</a>
                            @else
                                <span class="badge text-bg-light border">Gói Free</span>
                                <a href="{{ route('packages.index') }}" class="btn btn-sm btn-warning ms-auto">
                                    <i class="bi bi-gem me-1"></i>Mua gói cho con
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
@endsection
