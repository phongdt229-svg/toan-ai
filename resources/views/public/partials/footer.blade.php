<footer class="site-footer pt-5 pb-4 mt-auto">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <x-brand variant="light" size="lg" class="mb-3" />
                <p class="small mb-3">
                    Nền tảng học Toán trực tuyến lớp 1–12: lý thuyết, luyện tập, đề kiểm tra,
                    lộ trình cá nhân hoá và AI Tutor giúp học sinh hiểu bài thay vì chép đáp án.
                </p>

                <ul class="list-unstyled small d-grid gap-1 mb-0">
                    <li>
                        <i class="bi bi-envelope me-1"></i>
                        <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>
                    </li>
                    @if (config('site.hotline'))
                        <li><i class="bi bi-telephone me-1"></i>{{ config('site.hotline') }}</li>
                    @endif
                    @if (config('site.address'))
                        <li><i class="bi bi-geo-alt me-1"></i>{{ config('site.address') }}</li>
                    @endif
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <div class="site-footer__heading">Sản phẩm</div>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    <li><a href="{{ route('home') }}#tinh-nang">Tính năng</a></li>
                    <li><a href="{{ route('home') }}#ai-tutor">AI Tutor</a></li>
                    <li><a href="{{ route('home') }}#chuong-trinh">Chương trình</a></li>
                    <li><a href="{{ route('packages.index') }}">Gói học</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <div class="site-footer__heading">Tài khoản</div>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    <li><a href="{{ route('register.student') }}">Học sinh</a></li>
                    <li><a href="{{ route('register.teacher') }}">Giáo viên</a></li>
                    <li><a href="{{ route('register.parent') }}">Phụ huynh</a></li>
                    <li><a href="{{ route('login') }}">Đăng nhập</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <div class="site-footer__heading">Hỗ trợ</div>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    <li><a href="{{ route('password.request') }}">Quên mật khẩu</a></li>
                    <li><a href="{{ route('support.create') }}">Gửi yêu cầu hỗ trợ</a></li>
                    <li><a href="{{ route('support.create', ['loai' => 'content_error']) }}">Báo lỗi nội dung</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <div class="site-footer__heading">Pháp lý</div>
                <ul class="list-unstyled small d-grid gap-2 mb-0">
                    <li><a href="{{ route('legal.terms') }}">Điều khoản sử dụng</a></li>
                    <li><a href="{{ route('legal.privacy') }}">Chính sách bảo mật</a></li>
                </ul>
            </div>
        </div>

        <div class="site-footer__bottom mt-4 pt-3 d-flex flex-column flex-md-row gap-2 justify-content-between align-items-md-center small">
            <div>© {{ date('Y') }} {{ config('site.company') }}. Mọi quyền được bảo lưu.</div>
            <div class="d-flex flex-wrap gap-3">
                <span><i class="bi bi-shield-lock me-1"></i>Dữ liệu học tập được bảo mật</span>
                <span><i class="bi bi-wallet2 me-1"></i>Thanh toán qua MoMo</span>
            </div>
        </div>
    </div>
</footer>
