@extends('layouts.guest')

@section('title', 'Đăng ký giáo viên — TOÁN AI')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <a href="{{ route('register') }}" class="small text-decoration-none">
                <i class="bi bi-chevron-left"></i> Quay lại
            </a>

            <h1 class="h4 fw-bold mt-2 mb-2">Đăng ký tài khoản giáo viên</h1>
            <div class="alert alert-info small">
                <i class="bi bi-info-circle me-1"></i>
                Tài khoản giáo viên cần quản trị viên duyệt trước khi sử dụng.
            </div>

            <form method="POST" action="{{ route('register.teacher') }}" novalidate>
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
                    <label for="school" class="form-label">Trường / đơn vị</label>
                    <input type="text" id="school" name="school" value="{{ old('school') }}"
                           class="form-control form-control-lg @error('school') is-invalid @enderror" required>
                    @error('school') <div class="invalid-feedback">{{ $message }}</div> @enderror
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

                <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">Gửi đăng ký</button>
                <p class="small text-secondary text-center mt-3 mb-0">
                    Bằng việc tạo tài khoản, bạn đồng ý với
                    <a href="{{ route('legal.terms') }}">Điều khoản sử dụng</a> và
                    <a href="{{ route('legal.privacy') }}">Chính sách bảo mật</a>.
                </p>
            </form>
        </div>
    </div>
@endsection
