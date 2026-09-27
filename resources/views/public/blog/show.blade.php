@extends('layouts.public')

@section('title', $post->title . ' — TOÁN AI')
@section('meta_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 160))
@section('og_title', $post->title)
@section('og_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 160))
@if ($post->coverUrl())
    @section('og_image', $post->coverUrl())
@endif

@push('head')
    {{--
        JSON-LD Article + BreadcrumbList. Tác giả khai là tổ chức (Organization) chứ không phải
        Person — $post->author là tài khoản admin nội bộ, không phải bút danh công khai, khai
        Person ở đây là nói sai ai thực sự viết bài. @@context escape "@" để Blade không hiểu
        nhầm thành directive @context của Laravel (xem layouts/base.blade.php).
    --}}
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
                    'author' => ['@type' => 'Organization', 'name' => config('site.brand')],
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
                    @if ($post->author) · {{ $post->author->name }} @endif
                </div>
            </div>

            @if ($post->coverUrl())
                <img src="{{ $post->coverUrl() }}" alt="{{ $post->title }}" class="w-100 rounded-3 mb-4" style="max-height:420px;object-fit:cover">
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
                                    <img src="{{ $item->coverUrl() }}" alt="{{ $item->title }}" class="w-100 rounded-3 mb-3"
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
