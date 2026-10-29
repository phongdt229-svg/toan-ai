@php
    $courseName = 'Toán '.mb_strtolower($grade->name);
    $topic = $lesson->topic;
    $url = route('curriculum.lesson', [$grade, $lesson->slug]);
    $isFree = $lesson->access_level === \App\Models\Lesson::ACCESS_FREE;
    $difficulty = \App\Models\Question::DIFFICULTIES[$lesson->difficulty] ?? null;
    $description = $lesson->summary
        ?: 'Bài "'.$lesson->title.'" — '.$courseName.', chủ đề '.$topic->name.'. Lý thuyết, ví dụ, lỗi thường gặp và luyện tập chấm ngay.';
    $user = auth()->user();
@endphp

@extends('layouts.public')

@section('title', $lesson->title.' — '.$courseName.' — Math AI')
@section('meta_description', $description)
@section('og_title', $lesson->title.' — '.$courseName)

@push('head')
    {{--
        LearningResource: AI và Google biết bài thuộc lớp/chủ đề nào, dài bao lâu, có miễn phí không.
        Không khai nội dung bài — nội dung chỉ dành cho người đã đăng nhập (spec §6). @@context: xem layouts/base.
    --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@graph' => [
                array_filter([
                    '@type' => 'LearningResource',
                    'name' => $lesson->title,
                    'description' => $description,
                    'url' => $url,
                    'inLanguage' => 'vi',
                    'learningResourceType' => 'Bài học',
                    'educationalLevel' => $grade->name,
                    'teaches' => $topic->name,
                    'timeRequired' => $lesson->estimated_minutes ? 'PT'.$lesson->estimated_minutes.'M' : null,
                    'isAccessibleForFree' => $isFree,
                    'datePublished' => $lesson->published_at?->toIso8601String(),
                    'dateModified' => $lesson->updated_at?->toIso8601String(),
                    'provider' => ['@id' => url('/').'#organization'],
                    'isPartOf' => ['@type' => 'Course', 'name' => $courseName, 'url' => route('curriculum.grade', $grade)],
                ], fn ($v) => $v !== null),
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => route('home')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Chương trình', 'item' => route('curriculum.index')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $courseName, 'item' => route('curriculum.grade', $grade)],
                        ['@type' => 'ListItem', 'position' => 4, 'name' => $lesson->title, 'item' => $url],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container" style="max-width:820px">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="{{ route('curriculum.index') }}">Chương trình</a></li>
                    <li class="breadcrumb-item"><a href="{{ route('curriculum.grade', $grade) }}">{{ $courseName }}</a></li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $lesson->title }}</li>
                </ol>
            </nav>

            <div class="small text-secondary mb-1">{{ $topic->chapter->name }} · {{ $topic->name }}</div>
            <h1 class="h3 fw-bold mb-2">{{ $lesson->title }}</h1>

            <div class="d-flex flex-wrap gap-2 mb-3">
                <span class="badge text-bg-light border">{{ $grade->name }}</span>
                @if ($lesson->estimated_minutes)
                    <span class="badge text-bg-light border"><i class="bi bi-clock me-1"></i>{{ $lesson->estimated_minutes }} phút</span>
                @endif
                @if ($difficulty)
                    <span class="badge text-bg-light border">Độ khó: {{ $difficulty }}</span>
                @endif
                <span class="badge {{ $isFree ? 'text-bg-success' : 'text-bg-warning' }}">
                    {{ $isFree ? 'Miễn phí' : 'Gói '.(\App\Models\Package::TIER_LABELS[$lesson->access_level] ?? $lesson->access_level) }}
                </span>
            </div>

            @if ($lesson->summary)
                <p class="lead fs-6">{{ $lesson->summary }}</p>
            @endif

            @if ($outline['reading']->isNotEmpty() || $outline['interactive']->isNotEmpty())
                <section class="card border mb-4">
                    <div class="card-body">
                        <h2 class="h6 fw-bold mb-3">Bài học gồm</h2>
                        <ol class="list-unstyled d-grid gap-2 mb-0">
                            @foreach ($outline['reading'] as $section)
                                <li class="d-flex align-items-center gap-2">
                                    <i class="bi {{ $section->typeIcon() }} text-primary"></i>
                                    <span>{{ $section->title ?: $section->typeLabel() }}</span>
                                    <span class="badge text-bg-light border ms-auto">{{ $section->typeLabel() }}</span>
                                </li>
                            @endforeach
                            @foreach ($outline['interactive'] as $type => $count)
                                <li class="d-flex align-items-center gap-2">
                                    <i class="bi {{ \App\Models\LessonSection::ICONS[$type] ?? 'bi-pencil-square' }} text-primary"></i>
                                    <span>{{ \App\Models\LessonSection::TYPES[$type] ?? $type }}{{ $count > 1 ? ' ('.$count.' phần)' : '' }}</span>
                                    <span class="badge text-bg-light border ms-auto">Chấm ngay</span>
                                </li>
                            @endforeach
                        </ol>
                    </div>
                </section>
            @endif

            {{-- Spec §6: chưa đăng nhập bấm vào bài học → "Vui lòng đăng nhập để bắt đầu học." --}}
            <div class="card border-primary mb-4">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <i class="bi bi-lock text-primary" style="font-size:2rem"></i>
                    @if ($user?->isStudent())
                        <div class="flex-grow-1 fw-semibold">Bạn đã đăng nhập — vào học ngay.</div>
                        <a href="{{ route('student.lesson.show', $lesson->slug) }}" class="btn btn-primary btn-touch">Học bài này</a>
                    @elseif ($user)
                        <div class="flex-grow-1 text-secondary">Bài học dành cho tài khoản học sinh.</div>
                        <a href="{{ $user->homeRoute() }}" class="btn btn-outline-primary btn-touch">Về trang của bạn</a>
                    @else
                        <div class="flex-grow-1">
                            <div class="fw-semibold">Vui lòng đăng nhập để bắt đầu học.</div>
                            <div class="text-secondary small">
                                {{ $isFree ? 'Bài này học miễn phí — chỉ cần tạo tài khoản.' : 'Bài này thuộc gói '.(\App\Models\Package::TIER_LABELS[$lesson->access_level] ?? '').'.' }}
                            </div>
                        </div>
                        <div class="d-flex flex-wrap gap-2">
                            <a href="{{ route('login') }}" class="btn btn-outline-primary btn-touch">Đăng nhập</a>
                            <a href="{{ $isFree ? route('register') : route('packages.index') }}" class="btn btn-primary btn-touch">
                                {{ $isFree ? 'Đăng ký miễn phí' : 'Xem gói học' }}
                            </a>
                        </div>
                    @endif
                </div>
            </div>

            @php $siblings = $topic->lessons->where('id', '!=', $lesson->id); @endphp
            @if ($siblings->isNotEmpty())
                <h2 class="h6 fw-bold mb-2">Cùng chủ đề {{ $topic->name }}</h2>
                <ul class="list-unstyled d-grid gap-1">
                    @foreach ($siblings as $sibling)
                        <li>
                            <a href="{{ route('curriculum.lesson', [$grade, $sibling->slug]) }}" class="text-decoration-none">
                                <i class="bi bi-journal-text me-1"></i>{{ $sibling->title }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>
    </main>

    @include('public.partials.footer')
@endsection
