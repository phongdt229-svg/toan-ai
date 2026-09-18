@extends('layouts.guest')

@section('title', 'Quên mật khẩu — TOÁN AI')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 fw-bold mb-1">Quên mật khẩu</h1>
            <p class="text-secondary small mb-4">
                Nhập email bạn dùng để đăng nhập. Chúng tôi sẽ gửi link đặt lại mật khẩu.
            </p>

            <form method="POST" action="{{ route('password.email') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           class="form-control form-control-lg @error('email') is-invalid @enderror"
                           required autofocus autocomplete="email">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">Gửi link đặt lại</button>
            </form>

            <p class="text-center text-secondary small mt-4 mb-0">
                <a href="{{ route('login') }}">Quay lại đăng nhập</a>
            </p>
        </div>
    </div>

    <p class="text-center text-secondary small mt-3 mb-0">
        Tài khoản của học sinh do phụ huynh tạo? Hãy nhờ phụ huynh mở email đã đăng ký.
    </p>
@endsection
