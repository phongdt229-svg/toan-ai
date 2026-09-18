@extends('layouts.guest')

@section('title', 'Đăng ký phụ huynh — TOÁN AI')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <a href="{{ route('register') }}" class="small text-decoration-none">
                <i class="bi bi-chevron-left"></i> Quay lại
            </a>

            <h1 class="h4 fw-bold mt-2 mb-4">Đăng ký tài khoản phụ huynh</h1>

            <form method="POST" action="{{ route('register.parent') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Họ và tên</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           class="form-control form-control-lg @error('name') is-invalid @enderror" required autofocus>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           class="form-control form-control-lg @error('email') is-invalid @enderror"
                           required autocomplete="email">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="phone" class="form-label">Số điện thoại</label>
                    <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" inputmode="numeric"
                           class="form-control form-control-lg @error('phone') is-invalid @enderror" required>
                    @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="link_code" class="form-label">
                        Mã liên kết của con <span class="text-secondary fw-normal">(không bắt buộc)</span>
                    </label>
                    <input type="text" id="link_code" name="link_code" value="{{ old('link_code') }}"
                           maxlength="8" style="text-transform:uppercase"
                           class="form-control form-control-lg @error('link_code') is-invalid @enderror">
                    <div class="form-text">Mã 8 ký tự lấy trong tài khoản của con. Có thể liên kết sau.</div>
                    @error('link_code') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input type="password" id="password" name="password"
                           class="form-control form-control-lg @error('password') is-invalid @enderror"
                           required autocomplete="new-password">
                    <div class="form-text">Ít nhất 8 ký tự, gồm cả chữ và số.</div>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">Nhập lại mật khẩu</label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           class="form-control form-control-lg" required autocomplete="new-password">
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">Tạo tài khoản</button>
                <p class="small text-secondary text-center mt-3 mb-0">
                    Bằng việc tạo tài khoản, bạn đồng ý với
                    <a href="{{ route('legal.terms') }}">Điều khoản sử dụng</a> và
                    <a href="{{ route('legal.privacy') }}">Chính sách bảo mật</a>.
                </p>
            </form>
        </div>
    </div>
@endsection
