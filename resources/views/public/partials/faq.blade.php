{{--
    Câu hỏi thường gặp. Đặt NGAY SAU bảng giá: người ta xem giá xong mới sinh thắc mắc,
    trả lời ở đây là chặn đúng lúc họ định rời trang.

    Mỗi câu trả lời phải đúng với sản phẩm đang chạy — cùng luật với hero: không hứa
    thứ chưa có. Sửa tính năng nào thì soát lại mục tương ứng ở đây.
--}}
@php
    $faqs = [
        [
            'Không trả tiền thì dùng được gì?',
            'Gói Free cho phép học bài, luyện tập mỗi ngày và hỏi AI Tutor với số lượt giới hạn. '
            .'Không cần thẻ tín dụng, không tự động trừ tiền. Nâng cấp lúc nào thấy cần thì nâng.',
        ],
        [
            'Con tôi học lớp mấy thì dùng được?',
            'Từ lớp '.config('learning.grade_min').' đến lớp '.config('learning.grade_max').'. Chương trình chia theo chương và chủ đề bám sát chương trình phổ thông, '
            .'nên con học tới đâu là mở đúng phần đó.',
        ],
        [
            'AI có làm bài hộ con không?',
            'Không. AI Tutor gợi ý từng bước và giải thích chỗ sai để con tự làm được, không đưa sẵn đáp án. '
            .'Trong lúc con đang làm bài kiểm tra thì AI bị khoá hoàn toàn.',
        ],
        [
            'Tôi theo dõi việc học của con thế nào?',
            'Phụ huynh liên kết với tài khoản của con bằng mã con chia sẻ, rồi xem tiến độ, điểm và phần con '
            .'còn yếu. Mỗi tuần hệ thống gửi email báo cáo học tập cho phụ huynh đã liên kết.',
        ],
        [
            'Thanh toán và gia hạn ra sao?',
            'Thanh toán qua MoMo. Gói được kích hoạt ngay khi MoMo xác nhận. Mua thêm cùng hạng khi gói cũ '
            .'còn hạn thì thời gian được cộng nối tiếp, không mất ngày nào.',
        ],
        [
            'Dữ liệu học tập của con có an toàn không?',
            'Chúng tôi không bán dữ liệu cá nhân và không dùng dữ liệu học tập của học sinh để chạy quảng cáo. '
            .'Bạn xem chi tiết ở Chính sách bảo mật, và yêu cầu xoá tài khoản bất cứ lúc nào.',
        ],
    ];

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
