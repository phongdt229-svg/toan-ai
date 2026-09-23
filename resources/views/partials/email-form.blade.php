{{--
    Đổi email tài khoản. Dùng chung 4 portal.

    Hai bước chứ không đổi thẳng: địa chỉ mới chỉ có hiệu lực khi người dùng bấm được link
    gửi TỚI ĐỊA CHỈ ĐÓ. Gõ nhầm một ký tự mà đổi ngay là mất luôn đường đăng nhập và
    đường đặt lại mật khẩu — xem EmailChangeService.
--}}
@php
    // Tự lấy người đang đăng nhập thay vì nhận $user từ bên ngoài: bốn trang Cài đặt
    // truyền biến khác nhau, partial nào tự lo thì thêm vào trang mới không sợ thiếu.
    $user = auth()->user();
@endphp

<div class="card border mb-3" style="max-width:36rem">
    <div class="card-body">
        <h3 class="h6 fw-bold mb-3">Email đăng nhập</h3>

        <div class="d-flex justify-content-between align-items-center gap-2 mb-3">
            <div>
                <div class="fw-semibold">{{ $user->email }}</div>
                <div class="small text-secondary">
                    @if ($user->hasVerifiedEmail())
                        <i class="bi bi-patch-check-fill text-success me-1"></i>Đã xác thực
                    @else
                        <i class="bi bi-exclamation-circle text-warning me-1"></i>Chưa xác thực
                    @endif
                </div>
            </div>
        </div>

        @if ($user->pending_email)
            <div class="alert alert-warning small">
                <div>
                    Đang chờ xác nhận đổi sang <strong>{{ $user->pending_email }}</strong>.
                    Mở hộp thư đó và bấm link trong thư. Chưa bấm thì tài khoản vẫn dùng địa chỉ hiện tại.
                </div>
                <form method="POST" action="{{ route('email-change.destroy') }}" class="mt-2"
                      data-confirm="Bỏ yêu cầu đổi sang {{ $user->pending_email }}?" data-confirm-ok="Bỏ yêu cầu">
                    @csrf
                    @method('DELETE')
                    <button class="btn btn-sm btn-outline-secondary">Bỏ yêu cầu</button>
                </form>
            </div>
        @endif

        <form method="POST" action="{{ route('email-change.store') }}">
            @csrf

            <div class="mb-3">
                <label class="form-label" for="new_email">Email mới</label>
                <input type="email" id="new_email" name="email" required maxlength="191" autocomplete="email"
                       class="form-control @error('email') is-invalid @enderror" value="{{ old('email') }}">
                @error('email')<div class="invalid-feedback">{{ $message }}</div>@enderror
                <div class="form-text">Chúng tôi gửi thư xác nhận tới địa chỉ mới; bấm link trong thư là xong.</div>
            </div>

            <div class="mb-3">
                <label class="form-label" for="current_password_email">Mật khẩu hiện tại</label>
                <input type="password" id="current_password_email" name="current_password" required
                       autocomplete="current-password"
                       class="form-control @error('current_password') is-invalid @enderror" style="max-width:280px">
                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <button class="btn btn-primary btn-touch">Gửi xác nhận</button>
        </form>
    </div>
</div>
