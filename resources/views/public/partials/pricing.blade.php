<section class="section" id="goi-hoc">
    <div class="container">
        <h2 class="section__title mb-2">Gói <span class="hl">học</span></h2>
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
                @foreach ($packages as $t)
                    <div class="col-12 col-md-4">
                        @include('public.packages._tier', ['t' => $t, 'compact' => true])
                    </div>
                @endforeach
            </div>
        @endif
    </div>
</section>
