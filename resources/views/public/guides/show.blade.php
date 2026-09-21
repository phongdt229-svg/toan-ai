@extends('layouts.public')

@section('title', $article['title'] . ' — Hướng dẫn TOÁN AI')
@section('meta_description', $article['summary'])
{{-- Preview khi dán link bài hướng dẫn lên Zalo/Facebook: lấy đúng tên bài, bỏ hậu tố thương hiệu. --}}
@section('og_title', $article['title'])
@section('og_type', 'article')

@section('body')
    @include('public.partials.header')

    <main class="section">
        <div class="container" style="max-width:820px">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb small">
                    <li class="breadcrumb-item"><a href="{{ route('guides.index') }}">Hướng dẫn</a></li>
                    <li class="breadcrumb-item">
                        <a href="{{ route('guides.index', ['doi-tuong' => $article['audience']]) }}">
                            {{ $audiences[$article['audience']]['label'] }}
                        </a>
                    </li>
                    <li class="breadcrumb-item active" aria-current="page">{{ $article['title'] }}</li>
                </ol>
            </nav>

            <div class="d-flex align-items-start gap-3 mb-3">
                <span class="feature-card__icon mb-0"><i class="bi {{ $article['icon'] }}"></i></span>
                <div>
                    <h1 class="section__title mb-1">{{ $article['title'] }}</h1>
                    <p class="section__subtitle mb-0">{{ $article['summary'] }}</p>
                </div>
            </div>

            @if (! empty($article['warning']))
                <div class="alert alert-warning d-flex gap-2">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <div>{{ $article['warning'] }}</div>
                </div>
            @endif

            <ol class="guide-steps">
                @foreach ($article['steps'] as [$title, $body])
                    <li class="guide-steps__item">
                        <div class="guide-steps__title">{{ $title }}</div>
                        <p class="text-secondary mb-0">{{ $body }}</p>
                    </li>
                @endforeach
            </ol>

            @if (! empty($article['tips']))
                <div class="card border mt-4">
                    <div class="card-body">
                        <div class="fw-semibold mb-2"><i class="bi bi-lightbulb text-warning me-1"></i>Mẹo</div>
                        <ul class="mb-0 ps-3 d-grid gap-1 small">
                            @foreach ($article['tips'] as $tip)
                                <li>{{ $tip }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif

            @if (! empty($article['links']))
                <div class="d-flex flex-wrap gap-2 mt-4">
                    @foreach ($article['links'] as [$label, $target])
                        @php
                            // "guides.show:slug" = link sang bài hướng dẫn khác.
                            [$name, $param] = array_pad(explode(':', $target, 2), 2, null);
                            $url = $param ? route($name, $param) : route($name);
                        @endphp
                        <a href="{{ $url }}" class="btn btn-outline-primary btn-sm">
                            {{ $label }}<i class="bi bi-arrow-right ms-1"></i>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($related->isNotEmpty())
                <h2 class="h6 fw-bold mt-5 mb-2">Đọc tiếp</h2>
                <div class="row g-2">
                    @foreach ($related as $item)
                        <div class="col-12 col-md-6">
                            <a href="{{ route('guides.show', $item['slug']) }}" class="guide-card guide-card--compact">
                                <i class="bi {{ $item['icon'] }} text-primary"></i>
                                <span class="flex-grow-1">{{ $item['title'] }}</span>
                                <i class="bi bi-chevron-right text-secondary"></i>
                            </a>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="card border mt-4">
                <div class="card-body d-flex flex-wrap align-items-center gap-3">
                    <div class="flex-grow-1 small">
                        Hướng dẫn này chưa giải quyết được vấn đề của bạn?
                    </div>
                    <a href="{{ route('support.create', ['tu' => request()->fullUrl()]) }}" class="btn btn-sm btn-primary">
                        <i class="bi bi-life-preserver me-1"></i>Gửi yêu cầu hỗ trợ
                    </a>
                </div>
            </div>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
