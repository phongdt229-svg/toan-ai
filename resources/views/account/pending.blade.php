@extends('layouts.guest')

@section('title', 'Tài khoản chờ duyệt — TOÁN AI')

@section('content')
    <div class="card border-0 shadow-sm text-center">
        <div class="card-body p-4 p-md-5">
            <i class="bi bi-hourglass-split text-warning" style="font-size:3rem"></i>

            <h1 class="h4 fw-bold mt-3 mb-2">Tài khoản đang chờ duyệt</h1>
            <p class="text-secondary">
                Chào {{ $user->name }}, tài khoản giáo viên của bạn đã được gửi đi.
                Quản trị viên sẽ kiểm tra và kích hoạt trong thời gian sớm nhất.
            </p>

            @if ($user->teacherProfile?->reject_reason)
                <div class="alert alert-danger text-start small">
                    <strong>Lý do từ chối:</strong> {{ $user->teacherProfile->reject_reason }}
                </div>
            @endif

            <form method="POST" action="{{ route('logout') }}" class="mt-4">
                @csrf
                <button type="submit" class="btn btn-outline-secondary btn-touch">Đăng xuất</button>
            </form>
        </div>
    </div>
@endsection
