<section class="section" id="goi-hoc">
    <div class="container">
        <h2 class="section__title mb-2">Gói học</h2>
        <p class="section__subtitle mb-4">Bắt đầu miễn phí. Nâng cấp khi cần thêm bài tập và AI.</p>

        @if ($packages->isEmpty())
            {{-- Giá luôn lấy từ bảng `packages` (§18) — chưa có dữ liệu thì không bịa số. --}}
            <div class="row g-3">
                @foreach ([
                    ['Free', 'Một phần bài học, một số bài tập, AI giới hạn', false],
                    ['Pro', 'Toàn bộ bài học và bài tập, AI Tutor, đề kiểm tra, theo dõi tiến độ', true],
                    ['Premium', 'Tất cả của Pro + AI nâng cao + cá nhân hóa + báo cáo chi tiết', false],
                ] as [$name, $desc, $featured])
                    <div class="col-12 col-md-4">
                        <div class="price-card {{ $featured ? 'price-card--featured' : '' }}">
                            @if ($featured)
                                <span class="badge text-bg-primary mb-2">Phổ biến</span>
                            @endif
                            <h3 class="h5 fw-bold">{{ $name }}</h3>
                            <div class="price-card__price text-secondary mb-2">Sắp cập nhật</div>
                            <p class="text-secondary small">{{ $desc }}</p>
                            <a href="{{ route('register') }}" class="btn btn-outline-primary w-100 btn-touch">Đăng ký</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="row g-3">
                @foreach ($packages as $package)
                    <div class="col-12 col-md-4">
                        <div class="price-card {{ $package->tier === 'pro' ? 'price-card--featured' : '' }}">
                            <h3 class="h5 fw-bold">{{ $package->name }}</h3>
                            <div class="price-card__price">
                                {{ number_format((float) $package->price, 0, ',', '.') }}₫
                                <span class="fs-6 fw-normal text-secondary">/ {{ $package->duration_days }} ngày</span>
                            </div>
                            <p class="text-secondary small">{{ $package->description }}</p>
                            <a href="{{ route('register') }}" class="btn btn-outline-primary w-100 btn-touch">Chọn gói</a>
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
