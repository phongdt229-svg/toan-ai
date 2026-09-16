@php
    $features = [
        ['bi-journal-text', 'Lý thuyết', 'Trình bày ngắn gọn, công thức rõ ràng, đọc được trên điện thoại.'],
        ['bi-lightbulb', 'Ví dụ minh họa', 'Từng bước giải, kèm phần hiểu bản chất thay vì học vẹt.'],
        ['bi-pencil-square', 'Luyện tập', 'Bài tập phân theo độ khó, chấm tự động và giải thích ngay.'],
        ['bi-clipboard-check', 'Đề kiểm tra', 'Đề có bấm giờ, chấm điểm, xem lại từng câu sau khi nộp.'],
        ['bi-robot', 'AI Tutor', 'Gợi ý, giải thích, phân tích lỗi sai và cho bài tương tự.'],
        ['bi-graph-up', 'Tiến độ', 'Biểu đồ theo chủ đề: mạnh ở đâu, yếu ở đâu, học bao lâu.'],
    ];
@endphp

<section class="section" id="tinh-nang">
    <div class="container">
        <h2 class="section__title mb-2">Đủ mọi thứ cho một buổi học Toán</h2>
        <p class="section__subtitle mb-4">Từ đọc lý thuyết đến kiểm tra — tất cả trong một chỗ.</p>

        <div class="row g-3">
            @foreach ($features as [$icon, $title, $desc])
                <div class="col-12 col-sm-6 col-lg-4">
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
