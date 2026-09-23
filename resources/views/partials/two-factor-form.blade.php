{{-- Xác thực 2 bước (TOTP) — chỉ trang Cài đặt quản trị. Nhận $twoFactorEnabled, $twoFactorSecret, $twoFactorQr. --}}
<div class="card border mb-3" style="max-width:36rem">
    <div class="card-body">
        <h3 class="h6 fw-bold mb-2">Xác thực 2 bước</h3>

        @if (session('recovery_codes'))
            <div class="alert alert-warning small">
                <div class="fw-semibold mb-1">Lưu các mã dự phòng này ngay — sẽ không hiện lại.</div>
                Mỗi mã dùng được một lần khi mất điện thoại.
                <div class="font-monospace mt-2">
                    @foreach (session('recovery_codes') as $code)<div>{{ $code }}</div>@endforeach
                </div>
            </div>
        @endif

        @if ($twoFactorEnabled)
            <p class="small text-secondary">
                <i class="bi bi-shield-check text-success me-1"></i>Đang bật. Đăng nhập cần thêm mã từ ứng dụng xác thực.
            </p>

            <form method="POST" action="{{ route('admin.two-factor.regenerate') }}" class="mb-3">
                @csrf
                <label class="form-label small" for="pw-regen">Mật khẩu hiện tại</label>
                <div class="d-flex gap-2">
                    <input type="password" id="pw-regen" name="current_password" required class="form-control form-control-sm" style="max-width:220px" autocomplete="current-password">
                    <button class="btn btn-outline-secondary btn-sm">Tạo lại mã dự phòng</button>
                </div>
            </form>

            <form method="POST" action="{{ route('admin.two-factor.disable') }}"
                  data-confirm="Tắt xác thực 2 bước? Tài khoản sẽ kém an toàn hơn." data-confirm-ok="Tắt">
                @csrf
                @method('DELETE')
                <label class="form-label small" for="pw-off">Mật khẩu hiện tại</label>
                <div class="d-flex gap-2">
                    <input type="password" id="pw-off" name="current_password" required class="form-control form-control-sm" style="max-width:220px" autocomplete="current-password">
                    <button class="btn btn-outline-danger btn-sm">Tắt</button>
                </div>
            </form>
            @error('current_password')<div class="text-danger small mt-2">{{ $message }}</div>@enderror
        @elseif ($twoFactorSecret)
            <p class="small text-secondary">Quét mã QR bằng Google Authenticator, Authy hoặc 1Password, rồi nhập mã 6 số để xác nhận.</p>
            <div class="mb-2" style="max-width:200px">{!! $twoFactorQr !!}</div>
            <div class="small mb-3">Không quét được? Nhập tay khoá: <code>{{ $twoFactorSecret }}</code></div>

            <form method="POST" action="{{ route('admin.two-factor.confirm') }}" class="d-flex gap-2">
                @csrf
                <input name="code" inputmode="numeric" maxlength="6" required autocomplete="one-time-code"
                       class="form-control @error('code') is-invalid @enderror" style="max-width:160px" placeholder="123456">
                <button class="btn btn-primary">Xác nhận</button>
            </form>
            @error('code')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
        @else
            <p class="small text-secondary">
                Tài khoản quản trị đổi được giá gói và cấp gói — nên thêm một lớp bảo vệ ngoài mật khẩu.
            </p>
            <form method="POST" action="{{ route('admin.two-factor.enable') }}">
                @csrf
                <label class="form-label small" for="pw-on">Mật khẩu hiện tại</label>
                <div class="d-flex gap-2">
                    <input type="password" id="pw-on" name="current_password" required class="form-control form-control-sm" style="max-width:220px" autocomplete="current-password">
                    <button class="btn btn-primary btn-sm">Bắt đầu cài</button>
                </div>
                @error('current_password')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
            </form>
        @endif
    </div>
</div>
