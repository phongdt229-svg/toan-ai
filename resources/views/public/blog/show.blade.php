@extends('layouts.public')

@section('title', $post->title . ' — TOÁN AI')
@section('meta_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 160))
@section('og_title', $post->title)
@section('og_description', $post->excerpt ?: \Illuminate\Support\Str::limit(strip_tags($post->content), 160))
@if ($post->coverUrl())
    @section('og_image', $post->coverUrl())
@endif

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
                <img src="{{ $post->coverUrl() }}" alt="" class="w-100 rounded-3 mb-4" style="max-height:420px;object-fit:cover">
            @endif

            {{-- Nội dung đã lọc qua HtmlSanitizer lúc lưu (BlogPost::content mutator). --}}
            <div class="mb-5" data-math>{!! $post->content !!}</div>

            @if ($related->isNotEmpty())
                <hr class="mb-4">
                <h2 class="h6 fw-bold mb-3">Bài liên quan</h2>
                <div class="row g-3 mb-4">
                    @foreach ($related as $item)
                        <div class="col-12 col-sm-4">
                            <a href="{{ route('blog.show', $item) }}" class="text-decoration-none text-body small fw-semibold">
                                {{ $item->title }}
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    </main>

    @include('public.partials.footer')
@endsection
