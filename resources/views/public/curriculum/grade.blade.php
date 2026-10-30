@php
    $courseName = 'Toán '.mb_strtolower($grade->name);
    $lessons = $grade->subjects->flatMap->chapters->flatMap->topics->flatMap->lessons;
    $url = route('curriculum.grade', $grade);
    // Cấp học theo chương trình GDPT 2018 — người tìm hay gõ kèm ("toán lớp 6 THCS"), viết ra cho đúng ngữ cảnh.
    $stage = match (true) {
        $grade->level <= 5 => 'tiểu học',
        $grade->level <= 9 => 'THCS',
        default => 'THPT',
    };
@endphp

@extends('layouts.public')

@section('title', 'Học '.$courseName.' online — chương trình GDPT 2018 | Math AI')
@section('meta_description', 'Chương trình '.$courseName.' trên Math AI: '
    .$grade->subjects->flatMap->chapters->pluck('name')->take(6)->implode(', ')
    .($lessons->isNotEmpty() ? ' — '.$lessons->count().' bài học có ví dụ, luyện tập chấm ngay và AI Tutor.' : '.'))
{{-- Lớp chưa có bài nào chỉ là danh sách tên chương — trang mỏng, không cho lập chỉ mục (sitemap cũng bỏ qua). --}}
@if ($lessons->isEmpty())
    @section('robots', 'noindex,follow')
@endif

@push('head')
    @if ($lessons->isNotEmpty())
        {{-- Course + BreadcrumbList: AI biết khoá này dạy những chủ đề nào, bài nào miễn phí. @@context: xem layouts/base. --}}
        <script type="application/ld+json">
            {!! json_encode([
                '@@context' => 'https://schema.org',
                '@@graph' => [
                    [
                        '@type' => 'Course',
                        'name' => $courseName,
                        'description' => 'Chương trình '.$courseName.' bám sát chương trình phổ thông Việt Nam, chia theo chương và chủ đề.',
                        'url' => $url,
                        'inLanguage' => 'vi',
                        'educationalLevel' => $grade->name,
                        'provider' => ['@id' => url('/').'#organization'],
                        'teaches' => $grade->subjects->flatMap->chapters->flatMap->topics->filter(fn ($t) => $t->lessons->isNotEmpty())->pluck('name')->values()->all(),
                        'hasPart' => $lessons->map(fn ($lesson) => [
                            '@type' => 'LearningResource',
                            'name' => $lesson->title,
                            'url' => route('curriculum.lesson', [$grade, $lesson->slug]),
                            'isAccessibleForFree' => $lesson->access_level === \App\Models\Lesson::ACCESS_FREE,
                        ])->values()->all(),
                    ],
                    [
                        '@type' => 'BreadcrumbList',
                        'itemListElement' => [
                            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => route('home')],
                            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Chương trình', 'item' => route('curriculum.index')],
                            ['@type' => 'ListItem', 'position' => 3, 'name' => $courseName, 'item' => $url],
                        ],
                    ],
                ],
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
        </script>
    @endif
@endpush

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container" style="max-width:920px">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="{{ route('curriculum.index') }}">Chương trình</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $courseName }}</li>
                </ol>
            </nav>

            <h1 class="h3 fw-bold mb-2">Học {{ $courseName }} online</h1>
            @if ($lessons->isNotEmpty())
                {{-- Ngày cập nhật = bài học sửa gần nhất của lớp (dữ liệu thật, không phải lúc deploy). --}}
                @include('public.partials.page-byline', ['updated' => $lessons->max('updated_at')])
            @endif

            {{--
                Đoạn giới thiệu nhắm cụm "học toán lớp N online" (docs/SEO_KEYWORDS.md). Chỉ nói điều đúng:
                nội dung bám mạch kiến thức GDPT 2018 chứ KHÔNG chia theo một bộ sách — xem database/data/curriculum.php.
            --}}
            <p class="mb-2">
                Chương trình {{ $courseName }} ({{ $stage }}) trên Math AI bám theo chương trình giáo dục phổ thông 2018,
                (<a href="https://caselaw.vn/van-ban-phap-luat/341253-thong-tu-so-32-2018-tt-bgddt-ngay-26-12-2018-cua-bo-truong-bo-giao-duc-va-dao-tao-ve-chuong-trinh-giao-duc-pho-thong" target="_blank" rel="noopener">Thông tư 32/2018/TT-BGDĐT</a>),
                dùng được cùng sách Kết nối tri thức, Chân trời sáng tạo hay Cánh diều — tên chương có thể khác sách
                của con nhưng kiến thức là một. Mỗi bài có lý thuyết ngắn gọn, ví dụ giải từng bước, luyện tập chấm ngay
                và <a href="{{ route('ai-learning') }}">AI Tutor gợi ý khi con làm sai</a>.
            </p>
            <p class="text-secondary mb-4">
                @if ($lessons->isNotEmpty())
                    {{ $lessons->count() }} bài học, mỗi bài có lý thuyết, ví dụ, lỗi thường gặp và bài luyện tập chấm ngay.
                @else
                    Các bài học của lớp này đang được biên soạn. Dưới đây là khung chương trình.
                @endif
            </p>

            @foreach ($grade->subjects as $subject)
                @foreach ($subject->chapters as $chapter)
                    <section class="card border mb-3">
                        <div class="card-body">
                            <h2 class="h6 fw-bold mb-3">{{ $chapter->name }}</h2>

                            @forelse ($chapter->topics as $topic)
                                <div class="mb-3">
                                    <h3 class="small fw-semibold text-secondary text-uppercase mb-2">{{ $topic->name }}</h3>

                                    @if ($topic->lessons->isNotEmpty())
                                        <ul class="list-unstyled d-grid gap-1 mb-0">
                                            @foreach ($topic->lessons as $lesson)
                                                <li>
                                                    <a href="{{ route('curriculum.lesson', [$grade, $lesson->slug]) }}"
                                                       class="d-flex flex-wrap align-items-center gap-2 text-decoration-none text-body p-2 rounded border">
                                                        <i class="bi bi-journal-text text-primary"></i>
                                                        <span class="flex-grow-1">{{ $lesson->title }}</span>
                                                        @if ($lesson->estimated_minutes)
                                                            <span class="small text-secondary"><i class="bi bi-clock me-1"></i>{{ $lesson->estimated_minutes }} phút</span>
                                                        @endif
                                                        <span class="badge {{ $lesson->access_level === \App\Models\Lesson::ACCESS_FREE ? 'text-bg-success' : 'text-bg-light border' }}">
                                                            {{ \App\Models\Package::TIER_LABELS[$lesson->access_level] ?? $lesson->access_level }}
                                                        </span>
                                                    </a>
                                                </li>
                                            @endforeach
                                        </ul>
                                    @else
                                        <p class="small text-secondary mb-0">Đang biên soạn.</p>
                                    @endif
                                </div>
                            @empty
                                <p class="small text-secondary mb-0">Đang biên soạn.</p>
                            @endforelse
                        </div>
                    </section>
                @endforeach
            @endforeach

            <p class="text-secondary small mt-4">
                <i class="bi bi-info-circle me-1"></i>Tên chương có thể khác nhau giữa các bộ sách giáo khoa;
                nội dung kiến thức bám theo chương trình phổ thông.
            </p>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
