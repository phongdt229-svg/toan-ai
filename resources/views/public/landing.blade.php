@extends('layouts.public')

{{-- Title/H1 nhắm cụm "học toán online" (docs/SEO_KEYWORDS.md). Quản trị → SEO vẫn ghi đè được title. --}}
@section('title', 'Học Toán Online Lớp '.\App\Models\Grade::rangeLabel('–').' Cùng AI Tutor | Math AI')
@section('meta_description', 'Học Toán online lớp '.\App\Models\Grade::rangeLabel('–').' theo chương trình GDPT 2018: bài giảng, luyện tập chấm ngay, đề kiểm tra và AI gợi ý từng bước khi làm sai. Bắt đầu miễn phí.')
@section('html_class', 'landing-snap')

@php
    // Ngày SỬA NỘI DUNG trang chủ (E-E-A-T) — sửa chữ ở hero/FAQ/tính năng thì sửa ngày này.
    $landingUpdatedAt = '2026-10-30';
@endphp

@push('head')
    {{-- WebPage: ai biên soạn, sửa lúc nào. Organization/WebSite đã có ở layouts/base. @@context: xem layouts/base. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => route('home').'#webpage',
            'url' => route('home'),
            'name' => 'Học Toán online lớp '.\App\Models\Grade::rangeLabel('–').' cùng AI Tutor',
            'inLanguage' => 'vi',
            'isPartOf' => ['@id' => url('/').'#website'],
            'about' => ['@id' => url('/').'#organization'],
            'author' => \App\Support\PageAuthor::schema(),
            'dateModified' => $landingUpdatedAt,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
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
        @include('public.partials.sources', ['updated' => $landingUpdatedAt])
        @include('public.partials.news')
        @include('public.partials.cta')
    </main>

    @include('public.partials.footer')

    @push('scripts')
        @vite('resources/js/pwa-install.js')
    @endpush
@endsection
