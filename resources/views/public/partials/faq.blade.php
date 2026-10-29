{{--
    Câu hỏi thường gặp. Đặt NGAY SAU bảng giá: người ta xem giá xong mới sinh thắc mắc,
    trả lời ở đây là chặn đúng lúc họ định rời trang.

    Nội dung câu hỏi ở App\Support\LandingFaq (dùng chung với /llms.txt).
--}}
@php
    $faqs = \App\Support\LandingFaq::items();

    $faqJsonLd = json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn ($faq) => [
            '@type' => 'Question',
            'name' => $faq[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
        ], $faqs),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
@endphp

<section class="section section--muted" id="hoi-thuong-gap">
    <div class="container" style="max-width:52rem">
        <div class="text-center mb-4">
            <span class="section__eyebrow"><i class="bi bi-patch-question"></i>Hỏi thường gặp</span>
            <h2 class="section__title mb-2">Điều phụ huynh <span class="hl">hay hỏi nhất</span></h2>
            <p class="section__subtitle mx-auto">
                Chưa thấy câu của mình?
                <a href="{{ route('guides.index') }}">Xem hướng dẫn</a> hoặc
                <a href="{{ route('support.create') }}">gửi câu hỏi cho chúng tôi</a>.
            </p>
        </div>

        <div class="accordion" id="faq-accordion">
            @foreach ($faqs as $i => [$question, $answer])
                <div class="accordion-item">
                    <h3 class="accordion-header">
                        <button class="accordion-button @if ($i > 0) collapsed @endif" type="button"
                                data-bs-toggle="collapse" data-bs-target="#faq-{{ $i }}"
                                aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="faq-{{ $i }}">
                            {{ $question }}
                        </button>
                    </h3>
                    <div id="faq-{{ $i }}" class="accordion-collapse collapse @if ($i === 0) show @endif"
                         data-bs-parent="#faq-accordion">
                        <div class="accordion-body text-secondary">{{ $answer }}</div>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</section>

@push('scripts')
    {{--
        Dữ liệu có cấu trúc cho Google. Nội dung ở đây PHẢI trùng với phần hiện trên màn hình —
        Google phạt trang khai một đằng hiện một nẻo, nên hai chỗ cùng đọc từ một mảng $faqs.
    --}}
    <script type="application/ld+json">{!! $faqJsonLd !!}</script>
@endpush
