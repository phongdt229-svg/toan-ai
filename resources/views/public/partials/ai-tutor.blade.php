@php
    $modes = [
        ['bi-lightbulb', 'Gợi ý từng bước — không lộ kết quả'],
        ['bi-chat-square-text', 'Giải thích lại theo cách dễ hiểu hơn'],
        ['bi-check2-square', 'Kiểm tra đáp án và chỉ ra lỗi sai'],
        ['bi-arrow-repeat', 'Tạo bài tương tự để luyện thêm'],
        ['bi-search-heart', 'Phân tích lỗi để tìm kiến thức còn hổng'],
    ];
@endphp

<section class="section section--muted" id="ai-tutor">
    <div class="container">
        <div class="row g-4 g-lg-5 align-items-center">
            <div class="col-12 col-lg-6">
                <span class="section__eyebrow"><i class="bi bi-robot"></i>AI Tutor</span>
                <h2 class="section__title mb-2">AI <span class="hl--mark">không đưa đáp án</span>. AI <span class="hl">giúp hiểu</span>.</h2>
                <p class="section__subtitle mb-4">
                    Khi học sinh làm sai, AI phân tích sai ở bước nào, chỉ ra kiến thức bị hổng,
                    giải thích lại rồi cho một bài tương tự để luyện đúng chỗ đó.
                </p>

                <div class="row g-2">
                    @foreach ($modes as [$icon, $line])
                        <div class="col-12 col-sm-6">
                            <div class="d-flex align-items-start gap-2 bg-white border rounded-3 px-3 py-2 h-100">
                                <i class="bi {{ $icon }} text-primary"></i>
                                <span class="small">{{ $line }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>

                <p class="small text-secondary mt-3 mb-0">
                    <i class="bi bi-shield-check me-1"></i>Đang làm bài kiểm tra thì AI bị khoá — để điểm phản ánh đúng sức học.
                </p>

                <a href="{{ route('ai-learning') }}" class="btn btn-outline-primary mt-3">
                    Tìm hiểu cách học Toán với AI<i class="bi bi-arrow-right ms-1"></i>
                </a>
            </div>

            <div class="col-12 col-lg-6">
                @include('public.partials.ai-chat-demo')
            </div>
        </div>
    </div>
</section>
