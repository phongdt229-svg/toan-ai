@php
    $points = [
        ['bi-1-circle', 'Học lý thuyết', 'Đọc phần lý thuyết ngắn, xem ví dụ mẫu.'],
        ['bi-2-circle', 'Luyện tập', 'Làm quiz nhanh rồi tới bài tập theo độ khó.'],
        ['bi-3-circle', 'AI chữa lỗi', 'Sai chỗ nào, AI chỉ chỗ đó và giải thích lại.'],
        ['bi-4-circle', 'Ôn tập & kiểm tra', 'Ôn phần còn yếu rồi làm đề để tự đánh giá.'],
    ];
@endphp

<section class="section">
    <div class="container">
        <span class="badge text-bg-primary mb-2">Dành cho học sinh</span>
        <h2 class="section__title mb-2">Một chu trình học rõ ràng, không lan man</h2>
        <p class="section__subtitle mb-4">Giao diện ít chữ, nút to, mở điện thoại ra là học được ngay.</p>

        <div class="row g-3">
            @foreach ($points as [$icon, $title, $desc])
                <div class="col-12 col-sm-6 col-lg-3">
                    <div class="feature-card">
                        <div class="feature-card__icon"><i class="bi {{ $icon }}"></i></div>
                        <h3 class="h6 fw-bold">{{ $title }}</h3>
                        <p class="text-secondary small mb-0">{{ $desc }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
