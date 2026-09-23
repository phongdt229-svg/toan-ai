{{-- Dùng chung 4 portal — nhận $passwordRoute (route đích của form). --}}
<div class="card border mb-3" style="max-width:36rem">
    <div class="card-body">
        <h3 class="h6 fw-bold mb-3">Đổi mật khẩu</h3>
        <form method="POST" action="{{ $passwordRoute }}">
            @csrf
            @method('PUT')

            <div class="mb-3">
                <label class="form-label" for="current_password">Mật khẩu hiện tại</label>
                <input type="password" class="form-control @error('current_password') is-invalid @enderror"
                       id="current_password" name="current_password" required autocomplete="current-password">
                @error('current_password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password">Mật khẩu mới</label>
                <input type="password" class="form-control @error('password') is-invalid @enderror"
                       id="password" name="password" required autocomplete="new-password">
                <div class="form-text">Ít nhất 8 ký tự, có chữ và số.</div>
                @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
            </div>

            <div class="mb-3">
                <label class="form-label" for="password_confirmation">Nhập lại mật khẩu mới</label>
                <input type="password" class="form-control" id="password_confirmation"
                       name="password_confirmation" required autocomplete="new-password">
            </div>

            <button class="btn btn-outline-primary btn-touch">Đổi mật khẩu</button>
        </form>
    </div>
</div>
