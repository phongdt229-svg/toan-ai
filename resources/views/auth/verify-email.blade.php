@extends('layouts.guest')

@section('title', 'Xác thực email — TOÁN AI')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4 text-center">
            <i class="bi bi-envelope-check text-primary" style="font-size:2.5rem"></i>

            <h1 class="h4 fw-bold mt-3 mb-2">Xác thực email của bạn</h1>
            <p class="text-secondary">
                Chúng tôi đã gửi một link xác thực tới <strong>{{ auth()->user()->email }}</strong>.
                Mở hộp thư và bấm vào link đó (nhớ xem cả mục spam / quảng cáo).
            </p>

            <form method="POST" action="{{ route('verification.send') }}" class="d-grid gap-2">
                @csrf
                <button class="btn btn-primary btn-lg btn-touch">Gửi lại email xác thực</button>
            </form>

            <p class="small text-secondary mt-3 mb-0">
                Gõ nhầm email? Vào <a href="{{ route(auth()->user()->settingsRoute()) }}">Cài đặt</a> để sửa lại rồi gửi lại thư.
            </p>
        </div>
    </div>

    <p class="text-center small mt-3 mb-0">
        <a href="{{ auth()->user()->homeRoute() }}">Quay lại trang chính</a>
    </p>
@endsection
