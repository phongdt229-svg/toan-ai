@extends('layouts.public')

@section('title', 'Chương trình Toán lớp '.\App\Models\Grade::rangeLabel('–').' — Math AI')
@section('meta_description', 'Chương trình Toán lớp '.\App\Models\Grade::rangeLabel('–').' trên Math AI, bám sát chương trình phổ thông Việt Nam: từng lớp chia theo chương, chủ đề và bài học.')

@php
    // Nhóm theo cấp học như khối Chương trình ở trang chủ (public.partials.grades).
    $levels = array_values(array_filter([
        ['Tiểu học', $grades->where('level', '<=', 5)],
        ['THCS', $grades->whereBetween('level', [6, 9])],
        ['THPT', $grades->where('level', '>=', 10)],
    ], fn ($l) => $l[1]->isNotEmpty()));
@endphp

@push('head')
    {{-- ItemList các khoá "Toán lớp N" — AI đọc được site có những lớp nào, mỗi lớp ở URL nào. @@context: xem layouts/base. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@graph' => [
                [
                    '@type' => 'ItemList',
                    'name' => 'Chương trình Toán lớp '.\App\Models\Grade::rangeLabel('–'),
                    'itemListElement' => $grades->values()->map(fn ($grade, $i) => [
                        '@type' => 'ListItem',
                        'position' => $i + 1,
                        'url' => route('curriculum.grade', $grade),
                        'name' => 'Toán '.mb_strtolower($grade->name),
                    ])->all(),
                ],
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => route('home')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Chương trình', 'item' => route('curriculum.index')],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container">
            <div class="text-center mb-4">
                <span class="section__eyebrow"><i class="bi bi-diagram-3"></i>Chương trình</span>
                <h1 class="section__title mb-2">Chương trình Toán <span class="hl">lớp {{ \App\Models\Grade::rangeLabel() }}</span></h1>
                <p class="section__subtitle mx-auto">
                    Bám sát chương trình phổ thông. Mỗi lớp chia theo chương → chủ đề → bài học, đi từ dễ đến khó.
                </p>
            </div>

            @foreach ($levels as [$name, $items])
                <h2 class="h5 fw-bold mt-4 mb-3">{{ $name }}</h2>

                <div class="row g-3">
                    @foreach ($items as $grade)
                        <div class="col-12 col-md-6 col-xl-4">
                            <a href="{{ route('curriculum.grade', $grade) }}" class="guide-card">
                                <div class="feature-card__icon mb-0"><i class="bi bi-journal-bookmark"></i></div>
                                <div>
                                    <div class="fw-semibold mb-1">Toán {{ mb_strtolower($grade->name) }}</div>
                                    <p class="text-secondary small mb-0">
                                        @if ($grade->lessons_count > 0)
                                            {{ $grade->lessons_count }} bài học
                                        @else
                                            Đang biên soạn bài học
                                        @endif
                                    </p>
                                </div>
                                <i class="bi bi-chevron-right text-secondary ms-auto align-self-center"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endforeach

            <div class="card border-primary mt-5">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <i class="bi bi-rocket-takeoff text-primary" style="font-size:2rem"></i>
                    <div class="flex-grow-1">
                        <div class="fw-semibold">Bắt đầu học miễn phí</div>
                        <div class="text-secondary small">Tạo tài khoản để học bài, luyện tập chấm ngay và hỏi AI Tutor khi bí.</div>
                    </div>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-touch">Đăng ký miễn phí</a>
                </div>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
