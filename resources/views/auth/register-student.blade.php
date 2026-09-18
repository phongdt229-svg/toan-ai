@extends('layouts.guest')

@section('title', 'Đăng ký học sinh — TOÁN AI')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <a href="{{ route('register') }}" class="small text-decoration-none">
                <i class="bi bi-chevron-left"></i> Quay lại
            </a>

            <h1 class="h4 fw-bold mt-2 mb-1">Đăng ký tài khoản học sinh</h1>
            <p class="text-secondary small mb-4">
                Càng đầy đủ thông tin, AI càng cá nhân hóa việc học sát với bạn.
            </p>

            <form method="POST" action="{{ route('register.student') }}" novalidate>
                @csrf

                <h2 class="h6 fw-bold text-secondary text-uppercase small mb-3">Thông tin tài khoản</h2>

                <div class="mb-3">
                    <label for="name" class="form-label">Họ và tên <span class="text-danger">*</span></label>
                    <input type="text" id="name" name="name" value="{{ old('name') }}"
                           class="form-control form-control-lg @error('name') is-invalid @enderror"
                           required autofocus>
                    @error('name') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-3">
                    <label for="email" class="form-label">Email <span class="text-danger">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}"
                           class="form-control form-control-lg @error('email') is-invalid @enderror"
                           required autocomplete="email">
                    @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label for="phone" class="form-label">Số điện thoại</label>
                        <input type="tel" id="phone" name="phone" value="{{ old('phone') }}" inputmode="numeric"
                               class="form-control form-control-lg @error('phone') is-invalid @enderror">
                        @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="birth_date" class="form-label">Ngày sinh</label>
                        <input type="date" id="birth_date" name="birth_date" value="{{ old('birth_date') }}"
                               max="{{ now()->subYears(5)->toDateString() }}"
                               class="form-control form-control-lg @error('birth_date') is-invalid @enderror">
                        @error('birth_date') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu <span class="text-danger">*</span></label>
                    <input type="password" id="password" name="password"
                           class="form-control form-control-lg @error('password') is-invalid @enderror"
                           required autocomplete="new-password">
                    <div class="form-text">Ít nhất 8 ký tự, gồm cả chữ và số.</div>
                    @error('password') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="mb-4">
                    <label for="password_confirmation" class="form-label">
                        Nhập lại mật khẩu <span class="text-danger">*</span>
                    </label>
                    <input type="password" id="password_confirmation" name="password_confirmation"
                           class="form-control form-control-lg" required autocomplete="new-password">
                </div>

                <hr class="my-4">
                <h2 class="h6 fw-bold text-secondary text-uppercase small mb-3">Thông tin học tập</h2>

                <div class="row g-3 mb-3">
                    <div class="col-12 col-sm-6">
                        <label for="grade_id" class="form-label">Lớp <span class="text-danger">*</span></label>
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

                    <div class="col-12 col-sm-6">
                        <label for="school" class="form-label">Trường học</label>
                        <input type="text" id="school" name="school" value="{{ old('school') }}"
                               class="form-control form-control-lg @error('school') is-invalid @enderror">
                        @error('school') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <div class="mb-3">
                    <label for="address" class="form-label">Địa chỉ</label>
                    <input type="text" id="address" name="address" value="{{ old('address') }}"
                           placeholder="Quận/Huyện, Tỉnh/Thành phố"
                           class="form-control form-control-lg @error('address') is-invalid @enderror">
                    @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
                </div>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-6">
                        <label for="self_assessed_level" class="form-label">Học lực hiện tại</label>
                        <select id="self_assessed_level" name="self_assessed_level"
                                class="form-select form-select-lg @error('self_assessed_level') is-invalid @enderror">
                            <option value="">— Chọn —</option>
                            @foreach (\App\Models\StudentProfile::LEVELS as $value => $label)
                                <option value="{{ $value }}" @selected(old('self_assessed_level') === $value)>
                                    {{ $label }}
                                </option>
                            @endforeach
                        </select>
                        @error('self_assessed_level') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 col-sm-6">
                        <label for="math_average_score" class="form-label">Điểm TB môn Toán</label>
                        <input type="number" id="math_average_score" name="math_average_score"
                               value="{{ old('math_average_score') }}" step="0.1" min="0" max="10" placeholder="7.5"
                               class="form-control form-control-lg @error('math_average_score') is-invalid @enderror">
                        @error('math_average_score') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>

                <hr class="my-4">
                <h2 class="h6 fw-bold text-secondary text-uppercase small mb-3">Cá nhân hóa</h2>

                <fieldset class="mb-3">
                    <legend class="form-label fs-6">Bạn muốn học cùng ai?</legend>
                    <div class="row g-2">
                        @foreach (\App\Models\StudentProfile::PERSONAS as $value => $label)
                            <div class="col-6">
                                <input type="radio" class="btn-check" name="tutor_persona" id="persona-{{ $value }}"
                                       value="{{ $value }}" @checked(old('tutor_persona', 'co') === $value) required>
                                <label class="btn btn-outline-primary w-100 btn-touch" for="persona-{{ $value }}">
                                    <i class="bi bi-person-video3 d-block mb-1"></i>{{ $label }}
                                </label>
                            </div>
                        @endforeach
                    </div>
                    @error('tutor_persona') <div class="text-danger small mt-1">{{ $message }}</div> @enderror
                </fieldset>

                <div class="row g-3 mb-4">
                    <div class="col-12 col-sm-5">
                        <label for="favorite_color" class="form-label">Màu yêu thích</label>
                        <input type="color" id="favorite_color" name="favorite_color"
                               value="{{ old('favorite_color', '#2563eb') }}"
                               class="form-control form-control-color form-control-lg w-100">
                    </div>

                    <div class="col-12 col-sm-7">
                        <label for="interests" class="form-label">Sở thích</label>
                        <input type="text" id="interests" name="interests" value="{{ old('interests') }}"
                               placeholder="bóng đá, vẽ, game"
                               class="form-control form-control-lg @error('interests') is-invalid @enderror">
                        <div class="form-text">Cách nhau bằng dấu phẩy.</div>
                        @error('interests') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
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
