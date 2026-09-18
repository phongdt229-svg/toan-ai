<section class="section">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-12 col-lg-6 order-lg-2">
                <span class="section__eyebrow"><i class="bi bi-house-heart"></i>Dành cho phụ huynh</span>
                <h2 class="section__title mb-2">Biết con đang học tới đâu, yếu ở đâu</h2>
                <p class="section__subtitle mb-4">
                    Liên kết với tài khoản của con bằng một mã ngắn. Sau đó bạn xem được
                    tiến độ, điểm trung bình, thời gian học và những chủ đề con còn hổng —
                    kèm email tổng kết mỗi tuần.
                </p>

                <div class="row g-2 mb-4">
                    @foreach ([['Tiến độ', '72%', 'bi-graph-up'], ['Điểm TB', '8.1', 'bi-star'], ['Thời gian học', '12h30', 'bi-clock-history'], ['Bài hoàn thành', '32', 'bi-check2-all']] as [$label, $value, $icon])
                        <div class="col-6 col-sm-3">
                            <div class="stat-card h-100">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div class="stat-card__label">{{ $label }}</div>
                                    <i class="bi {{ $icon }} text-primary"></i>
                                </div>
                                <div class="stat-card__value">{{ $value }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>

                <a href="{{ route('register.parent') }}" class="btn btn-primary btn-touch">Tạo tài khoản phụ huynh</a>
                <p class="small text-secondary mt-2 mb-0">
                    <i class="bi bi-lock me-1"></i>Phụ huynh xem được kết quả học, không xem nội dung con trò chuyện riêng với AI.
                </p>
            </div>

            <div class="col-12 col-lg-6 order-lg-1">
                <div class="audience-card audience-card--parent">
                    <div class="audience-card__art">
                        @include('public.partials.illus.parent')
                    </div>
                    <div class="audience-card__body">
                        <div class="small text-secondary mb-1">AI đề xuất con ôn lại</div>
                        <div class="d-flex flex-wrap gap-2">
                            @foreach (['Phân số', 'Quy đồng mẫu số', 'So sánh phân số'] as $topic)
                                <span class="badge text-bg-light border">{{ $topic }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
