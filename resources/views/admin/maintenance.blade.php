@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Bảo trì — Quản trị TOÁN AI')
@section('page_title', 'Chế độ bảo trì')

@section('content')
    <div class="row g-3">
        <div class="col-12 col-xl-7">
            <div class="card border {{ $status ? 'border-warning' : '' }}">
                <div class="card-body">

                    @if ($status)
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge text-bg-warning">Đang bảo trì</span>
                            <span class="text-secondary small">Người dùng đang thấy trang "Hệ thống đang được nâng cấp".</span>
                        </div>

                        <dl class="row small mb-3">
                            <dt class="col-5 col-sm-4 fw-normal text-secondary">Bật lúc</dt>
                            <dd class="col-7 col-sm-8">
                                {{ $status['enabled_at']?->format('H:i d/m/Y') ?? '—' }}
                                @if ($status['enabled_at'])
                                    <span class="text-secondary">({{ $status['enabled_at']->diffForHumans() }})</span>
                                @endif
                            </dd>

                            <dt class="col-5 col-sm-4 fw-normal text-secondary">Người bật</dt>
                            <dd class="col-7 col-sm-8">{{ $status['enabled_by'] ?? '— (bật bằng dòng lệnh)' }}</dd>

                            <dt class="col-5 col-sm-4 fw-normal text-secondary">Báo với người dùng</dt>
                            <dd class="col-7 col-sm-8">Xong sau {{ $status['eta'] ?? config('site.maintenance_eta') }}</dd>
                        </dl>

                        @if ($status['secret'])
                            <label class="form-label small fw-semibold mb-1">Link xem site thật</label>
                            <p class="small text-secondary mb-1">
                                Gửi link này cho người cần kiểm tra bản mới. Mở một lần là trình duyệt đó đi qua
                                được trang bảo trì trong 12 giờ.
                            </p>
                            <div class="input-group input-group-sm mb-3">
                                <input class="form-control font-monospace" readonly
                                       value="{{ url($status['secret']) }}"
                                       onclick="this.select()">
                                <button class="btn btn-outline-secondary" type="button"
                                        onclick="navigator.clipboard.writeText(this.previousElementSibling.value); this.textContent='Đã chép';">
                                    Chép
                                </button>
                            </div>
                        @endif

                        <form method="POST" action="{{ route('admin.maintenance.destroy') }}">
                            @csrf
                            @method('DELETE')
                            <button class="btn btn-success">
                                <i class="bi bi-play-circle me-1"></i>Tắt bảo trì, mở lại site
                            </button>
                        </form>
                    @else
                        <div class="d-flex align-items-center gap-2 mb-3">
                            <span class="badge text-bg-success">Đang phục vụ bình thường</span>
                        </div>

                        <p class="small text-secondary">
                            Bật bảo trì khi cần cập nhật code hoặc xử lý sự cố. Mọi người dùng sẽ thấy trang
                            "Hệ thống đang được nâng cấp" thay cho trang họ đang mở.
                        </p>

                        <form method="POST" action="{{ route('admin.maintenance.store') }}"
                              data-confirm="Bật chế độ bảo trì ngay bây giờ? Người dùng sẽ không truy cập được." data-confirm-ok="Bật bảo trì">
                            @csrf

                            <div class="mb-3" style="max-width:280px">
                                <label class="form-label small" for="eta">Báo với người dùng là xong sau</label>
                                <input id="eta" name="eta" maxlength="50"
                                       class="form-control form-control-sm @error('eta') is-invalid @enderror"
                                       value="{{ old('eta', config('site.maintenance_eta')) }}"
                                       placeholder="15 phút">
                                @error('eta')<div class="invalid-feedback">{{ $message }}</div>@enderror
                                <div class="form-text">Viết tự nhiên: "30 phút", "sau 22h", "khoảng 1 tiếng".</div>
                            </div>

                            <div class="form-check mb-3">
                                <input class="form-check-input @error('confirm') is-invalid @enderror"
                                       type="checkbox" name="confirm" value="1" id="confirm">
                                <label class="form-check-label small" for="confirm">
                                    Tôi hiểu toàn bộ học sinh, giáo viên và phụ huynh sẽ không truy cập được cho tới khi tắt.
                                </label>
                                @error('confirm')<div class="invalid-feedback d-block">{{ $message }}</div>@enderror
                            </div>

                            <button class="btn btn-warning">
                                <i class="bi bi-cone-striped me-1"></i>Bật chế độ bảo trì
                            </button>
                        </form>
                    @endif

                </div>
            </div>
        </div>

        <div class="col-12 col-xl-5">
            <div class="card border h-100">
                <div class="card-body small">
                    <h3 class="h6 fw-bold mb-2">Khi bật bảo trì thì điều gì xảy ra?</h3>
                    <ul class="ps-3 mb-3">
                        <li class="mb-1">Mọi trang trả về mã 503 kèm trang bảo trì — công cụ tìm kiếm hiểu là "tạm thời", không hạ thứ hạng.</li>
                        <li class="mb-1">Học sinh đang làm bài <strong>không mất bài</strong>: đáp án đã gửi vẫn nằm trong DB, giờ làm bài tính theo <code>expires_at</code> phía máy chủ.</li>
                        <li class="mb-1">Hàng đợi (email, AI soạn bài) và các tác vụ định kỳ <strong>tạm dừng</strong>, chạy tiếp sau khi tắt.</li>
                        <li class="mb-1">MoMo gọi báo thanh toán không vào được sẽ gọi lại; lệnh đối soát <code>payments:expire-pending</code> cũng kiểm tra lại các đơn treo.</li>
                        <li>Trang đăng nhập và trang này vẫn mở, để bạn tắt được bảo trì từ máy khác.</li>
                    </ul>

                    <h3 class="h6 fw-bold mb-2">Nếu không vào lại được</h3>
                    <p class="mb-2">
                        Cookie bỏ qua chỉ sống 12 giờ và gắn với một trình duyệt. Mất cookie thì đăng nhập lại ở
                        <code>/dang-nhap</code> rồi mở thẳng <code>/quan-tri/bao-tri</code> — hai đường dẫn này cố ý
                        không bị chặn.
                    </p>
                    <p class="mb-0">
                        Trường hợp xấu nhất, trên máy chủ chạy <code>php artisan up</code> hoặc xoá
                        <code>storage/framework/down</code>.
                    </p>
                </div>
            </div>
        </div>
    </div>
@endsection
