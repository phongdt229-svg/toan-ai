<section class="hero">
    <span class="hero__blob hero__blob--1" aria-hidden="true"></span>
    <span class="hero__blob hero__blob--2" aria-hidden="true"></span>

    <div class="container hero__content">
        <div class="row align-items-center g-4 g-lg-5">
            <div class="col-12 col-lg-6">
                <span class="section__eyebrow">
                    <i class="bi bi-stars"></i>Lớp 1 → Lớp 12 · Bám sát chương trình
                </span>

                <h1 class="hero__title mb-3">
                    Học Toán thông minh<br class="d-none d-sm-block">
                    cùng <span class="hero__gradient-text">AI</span>
                </h1>

                <p class="hero__lead mb-4">
                    Lý thuyết dễ hiểu, ví dụ minh họa, luyện tập có chấm điểm và một AI Tutor
                    không đưa sẵn đáp án — mà giúp con <strong>hiểu vì sao</strong>.
                </p>

                <div class="d-grid d-sm-flex gap-2 mb-4">
                    <a href="{{ route('register') }}" class="btn btn-accent btn-lg btn-touch">
                        <i class="bi bi-rocket-takeoff"></i>Bắt đầu học miễn phí
                    </a>
                    <a href="#chuong-trinh" class="btn btn-outline-primary btn-lg btn-touch">Xem chương trình</a>
                </div>

                {{-- Chỉ nêu điều đúng với sản phẩm, không bịa số người dùng. --}}
                <div class="hero__stats">
                    @foreach ([
                        ['12', 'lớp từ 1 → 12'],
                        ['6', 'dạng câu hỏi'],
                        ['5', 'chế độ AI Tutor'],
                        ['0₫', 'để bắt đầu'],
                    ] as [$value, $label])
                        <div class="hero__stat">
                            <strong>{{ $value }}</strong>
                            <span>{{ $label }}</span>
                        </div>
                    @endforeach
                </div>

                <div class="d-flex flex-wrap gap-3 mt-3 text-secondary small">
                    <span><i class="bi bi-check-circle-fill text-success me-1"></i>Không cần thẻ tín dụng</span>
                    <span><i class="bi bi-phone me-1"></i>Cài được như ứng dụng trên điện thoại</span>
                </div>

                {{--
                    Ẩn mặc định — pwa-install.js chỉ hiện nút khi trình duyệt thực sự bắn
                    beforeinstallprompt (đủ điều kiện cài thật), tránh nút bấm không ra gì.
                --}}
                <button type="button" id="pwa-install-btn" class="btn btn-outline-primary btn-sm btn-touch mt-3 d-none">
                    <i class="bi bi-download me-1"></i>Cài đặt ứng dụng
                </button>
                <div id="pwa-ios-hint" class="small text-secondary mt-3 d-none">
                    <i class="bi bi-share me-1"></i>Trên iPhone: mở bằng Safari, bấm <strong>Chia sẻ</strong>
                    → <strong>Thêm vào MH chính</strong>.
                </div>
            </div>

            <div class="col-12 col-lg-6">
                <div class="device">
                    {{-- Hai thẻ này phải nói đúng thứ sản phẩm có thật: chấm tự động (GradingService)
                         và lộ trình riêng (LearningPathService). Trước đây là "+10 điểm" và "7 ngày liên tiếp"
                         trong khi hệ thống không hề có điểm thưởng hay chuỗi ngày học. --}}
                    <div class="device__chip device__chip--grade">
                        <i class="bi bi-patch-check-fill text-success"></i>Chấm ngay
                    </div>
                    <div class="device__chip device__chip--path">
                        <i class="bi bi-signpost-split-fill text-primary"></i>Lộ trình riêng
                    </div>

                    <div class="device__screen" data-math>
                        <div class="device__notch" aria-hidden="true"></div>

                        <div class="d-flex align-items-center justify-content-between mb-2">
                            <span class="badge text-bg-light border">Lớp 6 · Phân số</span>
                            <span class="small text-secondary"><i class="bi bi-clock me-1"></i>8 phút</span>
                        </div>

                        <div class="fw-bold mb-2">Cộng hai phân số khác mẫu</div>
                        <p class="small text-secondary mb-2">Quy đồng rồi cộng tử số:</p>
                        <p class="mb-3">$$\frac{1}{2} + \frac{1}{3} = \frac{3}{6} + \frac{2}{6} = \frac{5}{6}$$</p>

                        <div class="progress mb-3" style="height:6px" role="progressbar"
                             aria-label="Tiến độ bài học" aria-valuenow="72" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width:72%"></div>
                        </div>

                        <div class="border-top pt-3 d-flex gap-2 align-items-start">
                            <span class="feature-card__icon mb-0" style="width:36px;height:36px;font-size:1.125rem">
                                <i class="bi bi-robot"></i>
                            </span>
                            <div class="small">
                                <div class="fw-semibold">AI Tutor</div>
                                <div class="text-secondary">
                                    Em cộng thẳng tử với tử, mẫu với mẫu đúng không? Thử nghĩ xem:
                                    hai phần bánh chia khác nhau thì cộng trực tiếp được chưa?
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
