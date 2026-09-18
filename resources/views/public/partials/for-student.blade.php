@php
    $points = [
        ['bi-book', 'Học lý thuyết', 'Đọc phần lý thuyết ngắn, xem ví dụ mẫu.'],
        ['bi-pencil', 'Luyện tập', 'Làm quiz nhanh rồi tới bài tập theo độ khó.'],
        ['bi-robot', 'AI chữa lỗi', 'Sai chỗ nào, AI chỉ chỗ đó và giải thích lại.'],
        ['bi-trophy', 'Ôn tập & kiểm tra', 'Ôn phần còn yếu rồi làm đề để tự đánh giá.'],
    ];
@endphp

<section class="section">
    <div class="container">
        <div class="row g-5 align-items-center">
            <div class="col-12 col-lg-5">
                <span class="section__eyebrow"><i class="bi bi-mortarboard"></i>Dành cho học sinh</span>
                <h2 class="section__title mb-2">Một chu trình học rõ ràng, không lan man</h2>
                <p class="section__subtitle mb-4">
                    Giao diện ít chữ, nút to, mở điện thoại ra là học được ngay. Hệ thống tự nhớ
                    em đang học tới đâu và nhắc đúng phần còn yếu.
                </p>

                <div class="audience-card__art rounded-4 pb-3">
                    @include('public.partials.illus.student')
                </div>
            </div>

            <div class="col-12 col-lg-7">
                <div class="row g-3">
                    @foreach ($points as $i => [$icon, $title, $desc])
                        <div class="col-12 col-sm-6">
                            <div class="feature-card h-100">
                                <div class="d-flex align-items-center gap-2 mb-2">
                                    <div class="feature-card__icon mb-0"><i class="bi {{ $icon }}"></i></div>
                                    <span class="badge text-bg-light border">Bước {{ $i + 1 }}</span>
                                </div>
                                <h3 class="h6 fw-bold">{{ $title }}</h3>
                                <p class="text-secondary small mb-0">{{ $desc }}</p>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </div>
</section>
