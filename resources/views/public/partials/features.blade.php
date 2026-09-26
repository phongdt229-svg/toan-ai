@php
    $features = [
        ['bi-journal-text', '', 'Lý thuyết', 'Trình bày ngắn gọn, công thức rõ ràng, đọc được trên điện thoại.'],
        ['bi-lightbulb', 'feature-card--accent', 'Ví dụ minh họa', 'Từng bước giải, kèm phần hiểu bản chất thay vì học vẹt.'],
        ['bi-pencil-square', 'feature-card--success', 'Luyện tập', 'Bài tập phân theo độ khó, chấm tự động và giải thích ngay.'],
        ['bi-clipboard-check', '', 'Đề kiểm tra', 'Đề có bấm giờ, chấm điểm, xem lại từng câu sau khi nộp.'],
        ['bi-robot', 'feature-card--accent', 'AI Tutor', 'Gợi ý, giải thích, phân tích lỗi sai và cho bài tương tự.'],
        ['bi-graph-up-arrow', 'feature-card--success', 'Tiến độ', 'Biểu đồ theo chủ đề: mạnh ở đâu, yếu ở đâu, học bao lâu.'],
        // Hai mục dưới đây đều là tính năng ĐÃ CHẠY, không phải kế hoạch: QaService và
        // PlacementTestService + LearningPathService. "Cài như ứng dụng" có section riêng nổi bật
        // hơn ngay bên dưới (public.partials.pwa), không lặp lại ở đây.
        ['bi-chat-dots', '', 'Hỏi đáp', 'Hỏi bài công khai, thầy cô và các bạn cùng trả lời — có lời giải được chọn.'],
        ['bi-signpost-split', 'feature-card--accent', 'Lộ trình riêng', 'Làm bài kiểm tra đầu vào, hệ thống xếp lộ trình theo đúng sức học.'],
    ];
@endphp

<section class="section" id="tinh-nang">
    <div class="container">
        <div class="text-center mb-4">
            <span class="section__eyebrow"><i class="bi bi-grid-1x2"></i>Tính năng</span>
            <h2 class="section__title mb-2">Đủ mọi thứ cho <span class="hl">một buổi học Toán</span></h2>
            <p class="section__subtitle mx-auto">Từ đọc lý thuyết đến hỏi bài — tất cả trong một chỗ.</p>
        </div>

        <div class="row g-3">
            @foreach ($features as [$icon, $variant, $title, $desc])
                <div class="col-12 col-sm-6 col-lg-4">
                    <div class="feature-card {{ $variant }}">
                        <div class="feature-card__icon"><i class="bi {{ $icon }}"></i></div>
                        <h3 class="h6 fw-bold">{{ $title }}</h3>
                        <p class="text-secondary small mb-0">{{ $desc }}</p>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>
