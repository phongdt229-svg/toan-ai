@extends('layouts.guest')

@section('title', 'Đăng ký học sinh — TOÁN AI')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <a href="{{ route('register') }}" class="small text-decoration-none">
                <i class="bi bi-chevron-left"></i> Quay lại
            </a>

            <h1 class="h4 fw-bold mt-2 mb-4">Đăng ký tài khoản học sinh</h1>

            <form method="POST" action="{{ route('register.student') }}" novalidate>
                @csrf

                <div class="mb-3">
                    <label for="name" class="form-label">Họ và tên</label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           class="form-control form-control-lg @error('name') is-invalid @enderror"
                           required autofocus>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           class="form-control form-control-lg @error('email') is-invalid @enderror"
                           required autocomplete="email">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-7">
                        <label for="grade_id" class="form-label">Lớp</label>
                        <select id="grade_id" name="grade_id"
                                class="form-select form-select-lg @error('grade_id') is-invalid @enderror" required>
                            <option value="">— Chọn lớp —</option>
                            @foreach ($grades as $grade)
                                <option value="{{ $grade->id }}" @selected(old('grade_id') == $grade->id)>
                                    {{ $grade->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('grade_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-5">
                        <label for="birth_year" class="form-label">Năm sinh</label>
                        <input type="number" id="birth_year" name="birth_year" value="{{ old('birth_year') }}"
                               min="1990" max="{{ date('Y') }}" placeholder="{{ date('Y') - 12 }}"
                               class="form-control form-control-lg @error('birth_year') is-invalid @enderror">
                        @error('birth_year') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
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
            </form>
        </div>
    </div>
@endsection
