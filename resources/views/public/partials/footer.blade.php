<footer class="border-top py-4 py-lg-5">
    <div class="container">
        <div class="row g-4">
            <div class="col-12 col-lg-4">
                <div class="fw-bold text-primary mb-2">
                    <i class="bi bi-calculator me-1"></i>TOÁN AI
                </div>
                <p class="text-secondary small mb-0">
                    Nền tảng học Toán trực tuyến lớp 1–12, kết hợp lý thuyết, luyện tập,
                    đề kiểm tra và AI Tutor cá nhân hóa.
                </p>
            </div>

            <div class="col-6 col-lg-2">
                <div class="fw-semibold mb-2">Sản phẩm</div>
                <ul class="list-unstyled small d-grid gap-1 mb-0">
                    <li><a class="link-secondary text-decoration-none" href="#tinh-nang">Tính năng</a></li>
                    <li><a class="link-secondary text-decoration-none" href="#ai-tutor">AI Tutor</a></li>
                    <li><a class="link-secondary text-decoration-none" href="#goi-hoc">Gói học</a></li>
                </ul>
            </div>

            <div class="col-6 col-lg-2">
                <div class="fw-semibold mb-2">Tài khoản</div>
                <ul class="list-unstyled small d-grid gap-1 mb-0">
                    <li><a class="link-secondary text-decoration-none" href="{{ route('register.student') }}">Học sinh</a></li>
                    <li><a class="link-secondary text-decoration-none" href="{{ route('register.teacher') }}">Giáo viên</a></li>
                    <li><a class="link-secondary text-decoration-none" href="{{ route('register.parent') }}">Phụ huynh</a></li>
                </ul>
            </div>
        </div>

        <hr class="my-4">
        <div class="text-center text-secondary small">© {{ date('Y') }} TOÁN AI</div>
    </div>
</footer>
