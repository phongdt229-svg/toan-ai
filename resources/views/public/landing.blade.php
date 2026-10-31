{{--
    Trang chủ giống 100% mẫu docs/hoctoan-layout-v6: layout base (không nút nổi Zalo/Facebook), CSS riêng của mẫu,
    KHÔNG nạp Bootstrap/app.js (xem @section('assets') ở layouts/base). Nội dung nằm ở public/partials/landing-v6-*.
    Sửa mẫu thì sao chép lại nguyên văn vào các partial đó, đừng viết tay thêm.
--}}
@extends('layouts.base')

@section('title', 'Học Toán Online A.I – Hiểu năng lực, học đúng cách')
@section('meta_description', 'Khám phá cách học Toán theo năng lực cho học sinh lớp '.\App\Models\Grade::rangeLabel('–').'. Xem lộ trình học minh họa, cách trợ giảng A.I hướng dẫn và báo cáo dành cho phụ huynh.')

@php
    // Ngày SỬA NỘI DUNG trang chủ (E-E-A-T) — sửa chữ thì sửa ngày này.
    $landingUpdatedAt = '2026-10-31';
    $faqs = \App\Support\LandingFaq::items();
@endphp

@push('head')
    {{-- WebPage: ai biên soạn, sửa lúc nào. Organization/WebSite đã có ở layouts/base. @@context: xem layouts/base. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'WebPage',
            '@id' => route('home').'#webpage',
            'url' => route('home'),
            'name' => 'Học Toán Online A.I – Hiểu năng lực, học đúng cách',
            'inLanguage' => 'vi',
            'isPartOf' => ['@id' => url('/').'#website'],
            'about' => ['@id' => url('/').'#organization'],
            'author' => \App\Support\PageAuthor::schema(),
            'dateModified' => $landingUpdatedAt,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>

    {{-- FAQPage: PHẢI trùng khối FAQ hiển thị — cùng đọc từ App\Support\LandingFaq. --}}
    <script type="application/ld+json">
        {!! json_encode([
            '@@context' => 'https://schema.org',
            '@type' => 'FAQPage',
            'mainEntity' => array_map(fn ($faq) => [
                '@type' => 'Question',
                'name' => $faq[0],
                'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
            ], $faqs),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) !!}
    </script>
@endpush

@section('assets')
    @include('public.partials.landing-v6-style')
@endsection

@section('body')
    @include('public.partials.landing-v6-body')
    @include('public.partials.landing-v6-script')
@endsection
