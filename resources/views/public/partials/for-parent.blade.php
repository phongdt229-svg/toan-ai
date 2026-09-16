<section class="section">
    <div class="container">
        <div class="row g-4 align-items-center">
            <div class="col-12 col-lg-6 order-lg-2">
                <span class="badge text-bg-warning text-dark mb-2">Dành cho phụ huynh</span>
                <h2 class="section__title mb-2">Biết con đang học tới đâu, yếu ở đâu</h2>
                <p class="section__subtitle mb-4">
                    Liên kết với tài khoản của con bằng một mã ngắn. Sau đó bạn xem được
                    tiến độ, điểm trung bình, thời gian học và những chủ đề con còn hổng.
                </p>
                <a href="{{ route('register.parent') }}" class="btn btn-primary btn-touch">Tạo tài khoản phụ huynh</a>
            </div>

            <div class="col-12 col-lg-6 order-lg-1">
                <div class="bg-white border rounded-4 p-3 p-md-4">
                    <div class="fw-bold mb-3">Con của tôi</div>

                    <div class="row g-2 mb-3">
                        @foreach ([['Tiến độ', '72%'], ['Điểm TB', '8.1'], ['Thời gian học', '12h30'], ['Bài hoàn thành', '32']] as [$label, $value])
                            <div class="col-6">
                                <div class="stat-card">
                                    <div class="stat-card__label">{{ $label }}</div>
                                    <div class="stat-card__value">{{ $value }}</div>
                                </div>
                            </div>
                        @endforeach
                    </div>

                    <div class="small text-secondary mb-1">AI đề xuất ôn lại</div>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach (['Phân số', 'Quy đồng mẫu số', 'So sánh phân số'] as $topic)
                            <span class="badge text-bg-light border">{{ $topic }}</span>
                        @endforeach
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
