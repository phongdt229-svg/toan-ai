@extends('layouts.guest')

@section('title', 'Đặt mật khẩu mới — TOÁN AI')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 fw-bold mb-1">Đặt mật khẩu mới</h1>
            <p class="text-secondary small mb-4">Mật khẩu tối thiểu 8 ký tự, có cả chữ và số.</p>

            <form method="POST" action="{{ route('password.update') }}" novalidate>
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email', $email) }}"
                           class="form-control form-control-lg @error('email') is-invalid @enderror"
                           required autocomplete="email">
                    @error('email')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu mới</label>
                    <input type="password" id="password" name="password"
                           class="form-control form-control-lg @error('password') is-invalid @enderror"
                           required autofocus autocomplete="new-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">Nhập lại mật khẩu mới</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           class="form-control form-control-lg" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">Đổi mật khẩu</button>
            </form>

            <p class="text-secondary small mt-3 mb-0">
                <i class="bi bi-shield-lock me-1"></i>Đổi mật khẩu xong, mọi thiết bị đang đăng nhập sẽ bị đăng xuất.
            </p>
        </div>
    </div>
@endsection
