{{--
    Trang đích của cụm từ khoá "học toán với AI" (docs/SEO_KEYWORDS.md). Góc khác biệt: AI DẠY từng bước,
    không giải hộ — đúng hành vi thật của AI Tutor (PromptBuilder, TutorAccessGuard). Mọi câu ở đây phải
    còn đúng khi đọc code; câu hỏi thường gặp ở App\Support\AiTutorFaq (dùng chung với JSON-LD + llms.txt).
    Không ghi số lượt/giá — đọc ở trang Gói học, nguồn là DB.
--}}
@php
    $brand = config('site.brand');
    $faqs = \App\Support\AiTutorFaq::items();
    $url = route('ai-learning');
    // Ngày SỬA NỘI DUNG trang này — sửa chữ thì sửa luôn ngày (không dùng mtime: deploy nào cũng đổi).
    $updatedAt = '2026-10-30';

    // Nguồn trích dẫn — chỉ link đã kiểm tra mở được, số liệu chép đúng tóm tắt của bài báo.
    $study = [
        'name' => 'Generative AI without guardrails can harm learning: Evidence from high school mathematics',
        'authors' => 'Bastani H., Bastani O., Sungu A., Ge H., Kabakcı Ö., Mariman R.',
        'journal' => 'PNAS, tập 122, số 26 (2025)',
        'url' => 'https://doi.org/10.1073/pnas.2422633122',
    ];
    $sources = [
        [$study['authors'].' — “'.$study['name'].'”, '.$study['journal'].'.', $study['url']],
        ['Knowledge at Wharton — “Without Guardrails, Generative AI Can Harm Education” (27/08/2024).', 'https://knowledge.wharton.upenn.edu/article/without-guardrails-generative-ai-can-harm-education/'],
        ['Bộ Giáo dục và Đào tạo — Thông tư 32/2018/TT-BGDĐT ban hành Chương trình giáo dục phổ thông (26/12/2018).', 'https://caselaw.vn/van-ban-phap-luat/341253-thong-tu-so-32-2018-tt-bgddt-ngay-26-12-2018-cua-bo-truong-bo-giao-duc-va-dao-tao-ve-chuong-trinh-giao-duc-pho-thong'],
    ];

    $modes = [
        ['bi-lightbulb', 'Gợi ý từng bước', 'Kẹt ở đâu, AI đưa MỘT gợi ý cho bước kế tiếp và hỏi lại một câu để con tự nghĩ tiếp — không nói ra kết quả.'],
        ['bi-chat-square-text', 'Giải thích lại lời giải', 'Sau khi con đã làm, AI giảng từng bước, mỗi bước nói rõ vì sao làm vậy chứ không chỉ làm gì.'],
        ['bi-check2-square', 'Nhận xét bài làm', 'Hệ thống chấm đúng/sai, AI chỉ ra bước nào đúng, bước nào sai và cách sửa.'],
        ['bi-search-heart', 'Phân tích lỗi sai', 'Tìm ra con đang hiểu nhầm điều gì và kiến thức nền nào cần ôn lại.'],
        ['bi-arrow-repeat', 'Bài tương tự', 'Tạo một bài cùng dạng, khác số liệu để luyện lại đúng chỗ vừa sai.'],
        ['bi-camera', 'Chụp ảnh đề bài', 'Chụp đề trong sách, AI chép lại đề để hỏi tiếp — không giải luôn từ ảnh.'],
    ];

    $steps = [
        ['Tự làm trước', 'Con đọc lý thuyết, xem ví dụ rồi tự làm bài luyện tập. Hệ thống chấm ngay theo đáp án giáo viên soạn.'],
        ['Kẹt thì xin gợi ý', 'Không biết bắt đầu từ đâu? Bấm "Gợi ý" — AI chỉ mở hướng cho bước tiếp theo.'],
        ['Sai thì hỏi vì sao', 'Làm sai, con hỏi AI giải thích. AI chỉ ra sai ở bước nào và hổng kiến thức gì.'],
        ['Luyện lại cho chắc', 'Làm một bài tương tự cho tới khi đúng. Tiến độ và chủ đề còn yếu hiện cho cả phụ huynh xem.'],
    ];

    $compare = [
        ['Khi con kẹt', 'Đưa ngay lời giải đầy đủ', 'Đưa một gợi ý, con tự làm tiếp'],
        ['Chấm đúng/sai', 'AI tự đoán', 'Hệ thống chấm theo đáp án giáo viên'],
        ['Khi làm bài kiểm tra', 'Vẫn chụp ảnh giải được', 'AI bị khoá hoàn toàn'],
        ['Sau khi làm sai', 'Không biết con hổng chỗ nào', 'Chỉ ra lỗi + kiến thức cần ôn + bài tương tự'],
        ['Phạm vi', 'Hỏi gì cũng trả lời', 'Chỉ môn Toán, theo đúng bài con đang học'],
    ];
@endphp

@extends('layouts.public')

@section('title', 'Học Toán với AI — gợi ý từng bước, không giải hộ | '.$brand)
@section('meta_description', 'Học Toán với AI Tutor lớp '.\App\Models\Grade::rangeLabel('–').': gợi ý từng bước thay vì đưa đáp án, chỉ ra lỗi sai, tạo bài tương tự, khoá khi làm bài kiểm tra. Bắt đầu miễn phí.')
@section('og_title', 'Học Toán với AI — gợi ý từng bước, không giải hộ')

@push('head')
    {{-- WebPage + FAQPage + BreadcrumbList. FAQ trùng đúng khối hiển thị bên dưới (cùng mảng). @@context: xem layouts/base. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@graph' => [
                [
                    '@type' => 'WebPage',
                    '@id' => $url.'#webpage',
                    'url' => $url,
                    'name' => 'Học Toán với AI',
                    'inLanguage' => 'vi',
                    'isPartOf' => ['@id' => url('/').'#website'],
                    'about' => ['@id' => url('/').'#organization'],
                    'author' => \App\Support\PageAuthor::schema(),
                    'dateModified' => $updatedAt,
                    'citation' => [
                        ['@type' => 'ScholarlyArticle', 'name' => $study['name'], 'url' => $study['url'], 'isPartOf' => 'Proceedings of the National Academy of Sciences'],
                        ['@type' => 'Legislation', 'name' => 'Thông tư 32/2018/TT-BGDĐT — Chương trình giáo dục phổ thông', 'legislationDate' => '2018-12-26'],
                    ],
                ],
                [
                    '@type' => 'FAQPage',
                    'mainEntity' => array_map(fn ($faq) => [
                        '@type' => 'Question',
                        'name' => $faq[0],
                        'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
                    ], $faqs),
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => route('home')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Học Toán với AI', 'item' => $url],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('body')
    @include('public.partials.header')

    <main>
        <section class="section">
            <div class="container">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb small">
                        <li class="breadcrumb-item"><a href="{{ route('home') }}">Trang chủ</a></li>
                        <li class="breadcrumb-item active" aria-current="page">Học Toán với AI</li>
                    </ol>
                </nav>

                <div class="row g-4 g-lg-5 align-items-center">
                    <div class="col-12 col-lg-6">
                        <span class="section__eyebrow"><i class="bi bi-robot"></i>AI Tutor</span>
                        <h1 class="section__title mb-2">Học Toán với AI: <span class="hl">gợi ý từng bước</span>, không giải hộ</h1>
                        @include('public.partials.page-byline', ['updated' => $updatedAt])

                        {{-- Đoạn "trả lời nhanh": công cụ AI và đoạn trích nổi bật của Google hay lấy nguyên đoạn đầu trang. --}}
                        <p class="section__subtitle mb-4">
                            Học Toán với AI trên {{ $brand }} nghĩa là học sinh lớp {{ \App\Models\Grade::rangeLabel('–') }} có một
                            gia sư AI ngồi cạnh khi làm bài: kẹt thì AI gợi ý bước tiếp theo, sai thì AI chỉ ra sai ở đâu và
                            vì sao, rồi cho bài tương tự để luyện lại. AI không đưa sẵn đáp án, không tự chấm điểm và bị khoá
                            trong lúc làm bài kiểm tra.
                        </p>

                        <div class="d-grid d-sm-flex gap-2">
                            <a href="{{ route('register') }}" class="btn btn-accent btn-lg btn-touch">
                                <i class="bi bi-rocket-takeoff"></i>Học thử miễn phí
                            </a>
                            <a href="{{ route('curriculum.index') }}" class="btn btn-outline-primary btn-lg btn-touch">Xem chương trình</a>
                        </div>
                    </div>

                    <div class="col-12 col-lg-6">
                        @include('public.partials.ai-chat-demo')
                    </div>
                </div>
            </div>
        </section>

        <section class="section section--muted">
            <div class="container">
                <div class="text-center mb-4">
                    <h2 class="section__title mb-2">AI Tutor giúp con <span class="hl">những gì</span></h2>
                    <p class="section__subtitle mx-auto">Sáu cách dùng AI, cách nào cũng bắt con phải tự nghĩ trước.</p>
                </div>

                <div class="row g-3">
                    @foreach ($modes as [$icon, $title, $desc])
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

        <section class="section">
            <div class="container" style="max-width:52rem">
                <h2 class="section__title mb-2">AI giải hộ và AI <span class="hl">dạy từng bước</span> khác nhau thế nào?</h2>
                <p class="section__subtitle mb-4">
                    App chụp ảnh ra đáp án giúp nộp bài nhanh, nhưng đến giờ kiểm tra con vẫn không tự làm được.
                    Gia sư AI chỉ có ích khi nó bắt con suy nghĩ.
                </p>

                <div class="table-responsive">
                    <table class="table align-middle">
                        <thead>
                            <tr>
                                <th scope="col"></th>
                                <th scope="col">App giải toán bằng AI</th>
                                <th scope="col">AI Tutor của {{ $brand }}</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($compare as [$aspect, $solver, $tutor])
                                <tr>
                                    <th scope="row" class="small">{{ $aspect }}</th>
                                    <td class="small text-secondary">{{ $solver }}</td>
                                    <td class="small"><i class="bi bi-check-circle text-success me-1"></i>{{ $tutor }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        </section>

        <section class="section">
            <div class="container" style="max-width:52rem">
                <h2 class="section__title mb-2">Nghiên cứu nói gì về <span class="hl">AI gợi ý</span> và AI đưa đáp án</h2>
                <p class="mb-3">
                    Một thử nghiệm với gần 1.000 học sinh trung học môn Toán, công bố trên tạp chí khoa học PNAS năm 2025, so sánh
                    hai cách dùng GPT-4: một bản giống ChatGPT (hỏi là có lời giải) và một bản gia sư chỉ được gợi ý từng bước,
                    không đưa đáp án. Khi có AI bên cạnh, điểm bài luyện tập tăng 48% với bản ChatGPT và 127% với bản gia sư.
                    Nhưng đến bài kiểm tra không có AI, nhóm từng dùng bản ChatGPT làm <strong>kém hơn 17%</strong> so với
                    nhóm không dùng AI — còn nhóm dùng bản gia sư gợi ý thì gần như không bị ảnh hưởng.
                </p>

                <figure class="border-start border-4 border-primary ps-3 mb-3">
                    {{-- Không dùng class .blockquote: nó phóng 1.25rem làm câu dịch (.small = em) to hơn câu gốc. --}}
                    <blockquote class="mb-2" cite="https://knowledge.wharton.upenn.edu/article/without-guardrails-generative-ai-can-harm-education/">
                        <p class="fst-italic mb-1">“We’re really worried that if humans don’t learn, if they start using these tools as a crutch and rely on it…”</p>
                        <p class="small text-secondary mb-0">(Chúng tôi thật sự lo rằng nếu con người không học, nếu họ bắt đầu dùng những công cụ này như cái nạng và dựa vào nó…)</p>
                    </blockquote>
                    <figcaption class="blockquote-footer mb-0">
                        Hamsa Bastani, giáo sư Trường Wharton (Đại học Pennsylvania), đồng tác giả nghiên cứu —
                        <cite><a href="https://knowledge.wharton.upenn.edu/article/without-guardrails-generative-ai-can-harm-education/" target="_blank" rel="noopener">Knowledge at Wharton</a></cite>
                    </figcaption>
                </figure>

                <p class="text-secondary small mb-0">
                    Đó là lý do AI Tutor của {{ $brand }} được thiết kế theo hướng gợi ý: con vẫn phải tự làm, AI chỉ mở đường.
                    Nghiên cứu làm tại một trường trung học ở Thổ Nhĩ Kỳ nên chưa thể coi là kết luận cho mọi học sinh,
                    nhưng là bằng chứng rõ nhất hiện có về chuyện "AI giải hộ" ảnh hưởng tới việc học.
                </p>
            </div>
        </section>

        <section class="section section--muted">
            <div class="container" style="max-width:52rem">
                <h2 class="section__title mb-4">Một buổi học Toán <span class="hl">cùng AI</span> diễn ra thế nào</h2>

                <ol class="list-unstyled d-grid gap-3 mb-0">
                    @foreach ($steps as $i => [$title, $desc])
                        <li class="d-flex gap-3">
                            <span class="feature-card__icon flex-shrink-0 mb-0">{{ $i + 1 }}</span>
                            <div>
                                <h3 class="h6 fw-bold mb-1">{{ $title }}</h3>
                                <p class="text-secondary small mb-0">{{ $desc }}</p>
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>
        </section>

        <section class="section">
            <div class="container" style="max-width:52rem">
                <h2 class="section__title mb-2">An toàn khi cho con <span class="hl">học với AI</span></h2>
                <p class="section__subtitle mb-4">Điều phụ huynh lo nhất là con dựa vào AI. Vì vậy AI Tutor có sẵn các giới hạn:</p>

                <ul class="list-unstyled d-grid gap-2 mb-0">
                    <li><i class="bi bi-lock text-primary me-2"></i>Khoá hoàn toàn khi con đang làm bài kiểm tra; bài giáo viên giao chưa nộp thì chỉ được gợi ý.</li>
                    <li><i class="bi bi-clipboard-check text-primary me-2"></i>Điểm do hệ thống chấm theo đáp án giáo viên — AI không quyết định đúng sai.</li>
                    <li><i class="bi bi-calculator text-primary me-2"></i>Chỉ trả lời trong môn Toán; câu hỏi ngoài lề bị từ chối và ghi lại để quản trị xem xét.</li>
                    <li><i class="bi bi-image text-primary me-2"></i>Ảnh đề bài con chụp không được lưu, chỉ giữ phần chữ của đề.</li>
                    <li><i class="bi bi-people text-primary me-2"></i>Phụ huynh theo dõi tiến độ và chủ đề còn yếu; nội dung trò chuyện riêng của con với AI thì không đọc được.</li>
                </ul>

                <p class="small text-secondary mt-3 mb-0">
                    Chi tiết dữ liệu nào được gửi cho nhà cung cấp AI: <a href="{{ route('legal.privacy') }}">Chính sách bảo mật</a>.
                </p>
            </div>
        </section>

        <section class="section section--muted">
            <div class="container" style="max-width:52rem">
                <h2 class="section__title mb-2">Học Toán online với AI cho <span class="hl">lớp {{ \App\Models\Grade::rangeLabel('–') }}</span></h2>
                <p class="section__subtitle mb-0">
                    AI Tutor đi kèm từng bài trong chương trình Toán theo chương trình giáo dục phổ thông 2018 — lý thuyết,
                    ví dụ, luyện tập chấm ngay và đề kiểm tra.
                    <a href="{{ route('curriculum.index') }}">Xem chương trình từng lớp</a> hoặc
                    <a href="{{ route('packages.index') }}">so sánh các gói học</a>.
                </p>
            </div>
        </section>

        <section class="section">
            <div class="container" style="max-width:52rem">
                <h2 class="section__title mb-4">Câu hỏi thường gặp về <span class="hl">học Toán với AI</span></h2>

                <div class="accordion" id="ai-faq">
                    @foreach ($faqs as $i => [$question, $answer])
                        <div class="accordion-item">
                            <h3 class="accordion-header">
                                <button class="accordion-button @if ($i > 0) collapsed @endif" type="button"
                                        data-bs-toggle="collapse" data-bs-target="#ai-faq-{{ $i }}"
                                        aria-expanded="{{ $i === 0 ? 'true' : 'false' }}" aria-controls="ai-faq-{{ $i }}">
                                    {{ $question }}
                                </button>
                            </h3>
                            <div id="ai-faq-{{ $i }}" class="accordion-collapse collapse @if ($i === 0) show @endif"
                                 data-bs-parent="#ai-faq">
                                <div class="accordion-body text-secondary">{{ $answer }}</div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </section>

        <section class="section section--muted">
            <div class="container" style="max-width:52rem">
                <h2 class="h5 fw-bold mb-3">Nguồn tham khảo</h2>
                <ol class="small d-grid gap-2 mb-0">
                    @foreach ($sources as [$label, $link])
                        <li>{{ $label }} <a href="{{ $link }}" target="_blank" rel="noopener">{{ parse_url($link, PHP_URL_HOST) }}</a></li>
                    @endforeach
                </ol>
            </div>
        </section>

        @include('public.partials.cta')
    </main>

    @include('public.partials.footer')
@endsection
