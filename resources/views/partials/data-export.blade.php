{{-- Tải bản sao dữ liệu cá nhân (Chính sách bảo mật §7). Dùng chung 4 portal, đặt cạnh khu xoá tài khoản. --}}
<div class="card border mt-4">
    <div class="card-body">
        <h3 class="h6 fw-bold mb-2"><i class="bi bi-download me-1"></i>Tải bản sao dữ liệu</h3>
        <p class="small text-secondary">
            Nhận một file JSON gồm hồ sơ, tiến độ học, bài làm, lịch sử trò chuyện với AI và giao dịch của bạn.
            Không có mật khẩu hay thông tin nội bộ của hệ thống.
        </p>
        <form method="POST" action="{{ route('account.export') }}" class="d-flex flex-wrap gap-2 align-items-start">
            @csrf
            <div>
                <input type="password" name="current_password" required autocomplete="current-password"
                       class="form-control form-control-sm {{ old('_export') && $errors->has('current_password') ? 'is-invalid' : '' }}"
                       style="width:220px" placeholder="Mật khẩu hiện tại" aria-label="Mật khẩu hiện tại">
                @if (old('_export') && $errors->has('current_password'))
                    <div class="invalid-feedback">{{ $errors->first('current_password') }}</div>
                @endif
            </div>
            <input type="hidden" name="_export" value="1">
            <button class="btn btn-outline-primary btn-sm">Tải về</button>
        </form>
    </div>
</div>
