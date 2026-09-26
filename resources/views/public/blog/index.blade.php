@extends('layouts.public')

@section('title', ($activeCategory ? $activeCategory->name . ' — ' : '') . 'Tin tức — TOÁN AI')
@section('meta_description', 'Tin tức, bài giới thiệu và khuyến mãi mới nhất từ TOÁN AI.')

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container">
            <div class="text-center mb-4">
                <span class="section__eyebrow"><i class="bi bi-newspaper"></i>Tin tức</span>
                <h1 class="section__title mb-2">
                    @if ($activeCategory)
                        {{ $activeCategory->name }}
                    @else
                        Tin tức &amp; <span class="hl">khuyến mãi</span>
                    @endif
                </h1>
                <p class="section__subtitle mx-auto">Bài giới thiệu sản phẩm và tin khuyến mãi, sự kiện mới nhất.</p>
            </div>

            @if ($categories->isNotEmpty())
                <div class="d-flex flex-wrap justify-content-center gap-2 mb-4">
                    <a href="{{ route('blog.index') }}" class="btn btn-sm {{ $activeCategory ? 'btn-outline-secondary' : 'btn-primary' }}">Tất cả</a>
                    @foreach ($categories as $category)
                        <a href="{{ route('blog.index', ['danh-muc' => $category->slug]) }}"
                           class="btn btn-sm {{ $activeCategory?->id === $category->id ? 'btn-primary' : 'btn-outline-secondary' }}">
                            {{ $category->name }} <span class="opacity-75">({{ $category->posts_count }})</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($posts->isEmpty())
                <div class="alert alert-light border text-center">Chưa có bài viết nào{{ $activeCategory ? ' ở danh mục này' : '' }}.</div>
            @else
                <div class="row g-3">
                    @foreach ($posts as $post)
                        <div class="col-12 col-sm-6 col-lg-4">
                            <a href="{{ route('blog.show', $post) }}" class="feature-card d-block h-100 text-decoration-none text-body">
                                @if ($post->coverUrl())
                                    <img src="{{ $post->coverUrl() }}" alt="" class="w-100 rounded-3 mb-3"
                                         style="aspect-ratio:16/9;object-fit:cover">
                                @endif
                                <span class="badge text-bg-light border mb-2">{{ $post->category->name }}</span>
                                <h2 class="h6 fw-bold">{{ $post->title }}</h2>
                                @if ($post->excerpt)
                                    <p class="text-secondary small mb-2">{{ $post->excerpt }}</p>
                                @endif
                                <div class="text-secondary small">{{ $post->published_at->format('d/m/Y') }}</div>
                            </a>
                        </div>
                    @endforeach
                </div>

                <div class="mt-4">
                    <x-pagination :paginator="$posts" label="bài" />
                </div>
            @endif
        </div>
    </main>

    @include('public.partials.footer')
@endsection
