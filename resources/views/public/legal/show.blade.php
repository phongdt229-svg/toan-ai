{{--
    Trang chính sách (CMS, xem LegalPageService). Nội dung admin soạn đã lọc HTML lúc lưu nên in thẳng;
    trang hệ thống chưa soạn thì dùng bản mặc định trong code (tự đổi theo cấu hình).
    Trước khi phát hành thật: nhờ bộ phận pháp lý rà lại nội dung.
--}}
@extends('layouts.public')

@section('title', $page->title.' — Math AI')
@section('meta_description', $page->meta_description ?: $page->title.' của Math AI.')

@section('body')
    @include('public.partials.header')

    <main class="legal">
        <div class="container">
            <div class="legal__paper">
                @include('public.partials.breadcrumb', ['items' => [[$page->title, $page->url()]]])
                <span class="badge text-bg-light border mb-2">Cập nhật {{ $updatedLabel }}</span>
                <h1 class="h2 fw-bold mb-3">{{ $page->title }}</h1>

                @if ($usesDefault)
                    @include($defaultView)
                @else
                    {!! $page->content !!}
                @endif

                @if ($others->isNotEmpty())
                    <hr class="my-4">
                    <p class="small text-secondary mb-0">
                        Các chính sách khác:
                        @foreach ($others as $other)
                            <a href="{{ $other->url() }}">{{ $other->title }}</a>@if (! $loop->last) · @endif
                        @endforeach
                    </p>
                @endif
            </div>
        </div>
    </main>

    @include('public.partials.footer')
@endsection
