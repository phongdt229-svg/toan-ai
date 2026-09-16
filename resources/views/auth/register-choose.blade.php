@extends('layouts.guest')

@section('title', 'Đăng ký — TOÁN AI')

@section('content')
    <h1 class="h4 fw-bold text-center mb-1">Bạn là ai?</h1>
    <p class="text-secondary text-center small mb-4">Chọn loại tài khoản để tiếp tục.</p>

    <div class="d-grid gap-3">
        @foreach ([
            ['register.student', 'bi-mortarboard', 'Học sinh', 'Học lý thuyết, luyện tập và hỏi AI Tutor.'],
            ['register.teacher', 'bi-person-video3', 'Giáo viên', 'Soạn bài, quản lý lớp và giao bài tập.'],
            ['register.parent', 'bi-people', 'Phụ huynh', 'Theo dõi việc học của con.'],
        ] as [$route, $icon, $label, $desc])
            <a href="{{ route($route) }}" class="card border text-decoration-none text-body">
                <div class="card-body d-flex align-items-center gap-3 p-3">
                    <div class="feature-card__icon mb-0"><i class="bi {{ $icon }}"></i></div>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">{{ $label }}</div>
                        <div class="text-secondary small">{{ $desc }}</div>
                    </div>
                    <i class="bi bi-chevron-right text-secondary"></i>
                </div>
            </a>
        @endforeach
    </div>

    <p class="text-center text-secondary small mt-4 mb-0">
        Đã có tài khoản? <a href="{{ route('login') }}">Đăng nhập</a>
    </p>
@endsection
