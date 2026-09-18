@extends('layouts.guest')

@section('title', 'Đăng nhập — TOÁN AI')

@section('content')
    <div class="card border-0 shadow-sm">
        <div class="card-body p-4">
            <h1 class="h4 fw-bold mb-1">Đăng nhập</h1>
            <p class="text-secondary small mb-4">Tiếp tục việc học của bạn.</p>

            <form method="POST" action="{{ route('login') }}" novalidate>
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

                <div class="mb-3">
                    <label for="password" class="form-label">Mật khẩu</label>
                    <input type="password" id="password" name="password"
                           class="form-control form-control-lg @error('password') is-invalid @enderror"
                           required autocomplete="current-password">
                    @error('password')
                        <div class="invalid-feedback">{{ $message }}</div>
                    @enderror
                </div>

                <div class="d-flex justify-content-between align-items-center mb-4">
                    <div class="form-check mb-0">
                        <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                        <label class="form-check-label" for="remember">Ghi nhớ đăng nhập</label>
                    </div>
                    <a href="{{ route('password.request') }}" class="small">Quên mật khẩu?</a>
                </div>

                <button type="submit" class="btn btn-primary btn-lg w-100 btn-touch">Đăng nhập</button>
            </form>

            <p class="text-center text-secondary small mt-4 mb-0">
                Chưa có tài khoản? <a href="{{ route('register') }}">Đăng ký ngay</a>
            </p>
        </div>
    </div>

    {{-- Chỉ render khi APP_ENV=local (controller trả mảng rỗng ở môi trường khác). --}}
    @if (! empty($demoAccounts))
        <div class="card border-warning mt-3">
            <div class="card-body p-3">
                <div class="d-flex align-items-center gap-2 mb-2">
                    <i class="bi bi-cone-striped text-warning"></i>
                    <span class="fw-semibold small">Tài khoản demo — chỉ hiện ở môi trường local</span>
                </div>
                <div class="small text-secondary mb-2">
                    Mật khẩu chung: <code>{{ config('app.demo_password') }}</code> · Bấm để điền sẵn, bấm đúp để đăng nhập luôn.
                </div>

                <div class="d-grid gap-2">
                    @foreach ($demoAccounts as $account)
                        {{-- .btn mặc định nowrap → cho xuống dòng để không tràn khung trên màn hẹp. --}}
                        <button type="button" class="btn btn-sm btn-outline-secondary text-start w-100"
                                style="white-space:normal"
                                data-demo-email="{{ $account['email'] }}" data-demo-password="{{ config('app.demo_password') }}">
                            <span class="d-flex align-items-center gap-2">
                                <span class="fw-semibold">{{ $account['role'] }}</span>
                                <span class="text-secondary small text-truncate" style="min-width:0">{{ $account['name'] }}</span>
                                @if ($account['status'] !== 'active')
                                    <span class="badge text-bg-warning ms-auto">{{ $account['status'] }}</span>
                                @endif
                            </span>
                            <code class="d-block small text-break">{{ $account['email'] }}</code>
                        </button>
                    @endforeach
                </div>
            </div>
        </div>

        @push('scripts')
            <script>
                document.querySelectorAll('[data-demo-email]').forEach((btn) => {
                    const fill = () => {
                        document.getElementById('email').value = btn.dataset.demoEmail;
                        document.getElementById('password').value = btn.dataset.demoPassword;
                    };
                    btn.addEventListener('click', fill);
                    btn.addEventListener('dblclick', () => { fill(); btn.closest('body').querySelector('form[action$="/dang-nhap"]').submit(); });
                });
            </script>
        @endpush
    @endif
@endsection
