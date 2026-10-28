@extends('layouts.public')

@section('title', 'TOÁN AI — Học Toán thông minh cùng AI')
@section('meta_description', 'Học Toán lớp '.\App\Models\Grade::rangeLabel('–').' theo đúng chương trình: bài giảng, luyện tập chấm ngay, đề kiểm tra và AI giảng lại từng bước khi làm sai.')
@section('html_class', 'landing-snap')

{{-- Công thức ở hero là phần tử LCP; KaTeX khai font-display:block nên chữ ẩn tới khi font về — tải trước để hiện sớm. --}}
@push('head')
    <link rel="preload" as="font" type="font/woff2" crossorigin href="{{ Vite::asset('node_modules/katex/dist/fonts/KaTeX_Main-Regular.woff2') }}">
@endpush

@push('scripts')
    @vite('resources/js/install-app.js')
@endpush

@section('body')
    @include('public.partials.header')

    <main>
        @include('public.partials.hero')
        @include('public.partials.features')
        @include('public.partials.ai-tutor')
        @include('public.partials.pwa')
        @include('public.partials.role-nav')
        @include('public.partials.for-student')
        @include('public.partials.for-teacher')
        @include('public.partials.for-parent')
        @include('public.partials.grades')
        @include('public.partials.pricing')
        @include('public.partials.faq')
        @include('public.partials.news')
        @include('public.partials.cta')
    </main>

    @include('public.partials.footer')

    @push('scripts')
        @vite('resources/js/pwa-install.js')
    @endpush
@endsection
