@extends('layouts.public')

@section('title', $post->title . ' — Math AI')
@section('meta_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 160))
@section('og_title', $post->title)
@if ($post->byline_name)
    @section('author', $post->byline_name)
@endif
@section('og_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 160))
@if ($post->coverUrl())
    @section('og_image', $post->coverUrl())
@endif

@push('head')
    {{--
        JSON-LD Article + BreadcrumbList. Tác giả: Person khi admin khai người biên soạn công khai
        (byline_name — tín hiệu "ai viết" mà Google/AI dùng để tin bài), không thì Organization.
        KHÔNG lấy $post->author: đó là tài khoản admin nội bộ, khai Person theo nó là nói sai ai viết bài.
        @@context escape "@" để Blade không hiểu nhầm thành directive @context của Laravel (xem layouts/base.blade.php).
    --}}
    @php
        $articleAuthor = $post->byline_name
            ? array_filter(['@type' => 'Person', 'name' => $post->byline_name, 'jobTitle' => $post->byline_title,
                'worksFor' => ['@id' => url('/').'#organization']])
            : ['@type' => 'Organization', 'name' => config('site.brand')];
    @endphp
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@@graph' => [
                array_filter([
                    '@type' => 'Article',
                    'headline' => $post->title,
                    'description' => $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 160),
                    'image' => $post->coverUrl() ?: null,
                    'datePublished' => $post->published_at->toIso8601String(),
                    'dateModified' => $post->updated_at->toIso8601String(),
                    'mainEntityOfPage' => route('blog.show', $post->slug),
                    'author' => $articleAuthor,
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => config('site.brand'),
                        'logo' => ['@type' => 'ImageObject', 'url' => asset('icons/icon-512.png')],
                    ],
                ]),
                [
                    '@type' => 'BreadcrumbList',
                    'itemListElement' => [
                        ['@type' => 'ListItem', 'position' => 1, 'name' => 'Trang chủ', 'item' => route('home')],
                        ['@type' => 'ListItem', 'position' => 2, 'name' => 'Tin tức', 'item' => route('blog.index')],
                        ['@type' => 'ListItem', 'position' => 3, 'name' => $post->category->name, 'item' => route('blog.index', ['danh-muc' => $post->category->slug])],
                        ['@type' => 'ListItem', 'position' => 4, 'name' => $post->title, 'item' => route('blog.show', $post->slug)],
                    ],
                ],
            ],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container" style="max-width:760px">
            <a href="{{ route('blog.index') }}" class="small text-decoration-none">
                <i class="bi bi-chevron-left"></i> Tin tức
            </a>

            <div class="mt-3 mb-4">
                <a href="{{ route('blog.index', ['danh-muc' => $post->category->slug]) }}"
                   class="badge text-bg-light border text-decoration-none mb-2 d-inline-block">{{ $post->category->name }}</a>
                <h1 class="section__title mb-2">{{ $post->title }}</h1>
                <div class="text-secondary small">
                    {{ $post->published_at->format('d/m/Y') }}
                    @if ($post->byline_name)
                        · Biên soạn: <span class="fw-semibold text-body">{{ $post->byline_name }}</span>@if ($post->byline_title), {{ $post->byline_title }}@endif
                    @elseif ($post->author)
                        · {{ $post->author->name }}
                    @endif
                </div>
            </div>

            @if ($post->coverUrl())
                <img src="{{ $post->coverUrl() }}" alt="{{ $post->title }}" class="w-100 rounded-3 mb-4" fetchpriority="high" style="max-height:420px;object-fit:cover">
            @endif

            {{-- Nội dung đã lọc qua HtmlSanitizer lúc lưu (BlogPost::content mutator). --}}
            <div class="mb-5" data-math>{!! $post->content !!}</div>

            @if ($related->isNotEmpty())
                <hr class="mb-4">
                <h2 class="h6 fw-bold mb-3">Bài liên quan</h2>
                <div class="row g-3 mb-4">
                    @foreach ($related as $item)
                        <div class="col-12 col-sm-4">
                            <a href="{{ route('blog.show', $item) }}" class="feature-card d-block h-100 text-decoration-none text-body">
                                @if ($item->coverUrl())
                                    <img src="{{ $item->coverUrl() }}" alt="{{ $item->title }}" class="w-100 h-auto rounded-3 mb-3" width="640" height="360" loading="lazy" decoding="async"
                                         style="aspect-ratio:16/9;object-fit:cover">
                                @endif
                                <div class="small fw-semibold">{{ $item->title }}</div>
                                <div class="text-secondary small mt-1">{{ $item->published_at->format('d/m/Y') }}</div>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>

    @include('public.partials.footer')
@endsection
