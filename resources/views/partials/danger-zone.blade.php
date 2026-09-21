{{--
    Vùng nguy hiểm trong trang Cài đặt: xoá tài khoản.
    Chính sách bảo mật cam kết cho người dùng yêu cầu xoá — đây là nơi thực hiện cam kết đó.
--}}
@php use App\Services\Auth\AccountDeletionService; @endphp

<div class="card border-danger mt-4">
    <div class="card-body">
        <h3 class="h6 fw-bold text-danger mb-2">
            <i class="bi bi-exclamation-octagon me-1"></i>Xoá tài khoản
        </h3>

        <p class="small text-secondary">
            Tài khoản ngừng truy cập ngay lập tức. Dữ liệu cá nhân (hồ sơ, nội dung trò chuyện với AI)
            bị xoá vĩnh viễn sau <strong>{{ AccountDeletionService::GRACE_DAYS }} ngày</strong> —
            trong thời gian đó bạn vẫn đổi ý được bằng cách liên hệ
            <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>.
            Hoá đơn được giữ theo thời hạn kế toán mà pháp luật yêu cầu.
        </p>

        @if (auth()->user()->isTeacher())
            <p class="small text-secondary">
                <i class="bi bi-info-circle me-1"></i>Bài học, câu hỏi và lớp bạn đã tạo vẫn ở lại hệ thống để
                học sinh không mất dữ liệu học, nhưng sẽ không còn gắn với tên bạn.
            </p>
        @endif

        <button class="btn btn-outline-danger btn-sm" type="button" data-bs-toggle="collapse" data-bs-target="#delete-account">
            Tôi muốn xoá tài khoản
        </button>

        <div class="collapse mt-3" id="delete-account">
            <form method="POST" action="{{ route('account.destroy') }}"
                  onsubmit="return confirm('Xoá tài khoản này? Thao tác không thể tự hoàn tác.')">
                @csrf
                @method('DELETE')

                <div class="mb-2">
                    <label class="form-label small" for="delete_reason">Lý do (không bắt buộc, giúp chúng tôi cải thiện)</label>
                    <input id="delete_reason" name="reason" maxlength="500" class="form-control form-control-sm">
                </div>

                <div class="mb-2">
                    <label class="form-label small" for="delete_password">Nhập mật khẩu để xác nhận</label>
                    <input id="delete_password" name="password" type="password" required autocomplete="current-password"
                           class="form-control form-control-sm @error('password') is-invalid @enderror" style="max-width:280px">
                    @error('password')<div class="invalid-feedback">{{ $message }}</div>@enderror
                </div>

                <button class="btn btn-danger btn-sm">Xoá tài khoản của tôi</button>
            </form>
        </div>
    </div>
</div>
