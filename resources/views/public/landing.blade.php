@extends('layouts.public')

@section('title', 'TOÁN AI — Học Toán thông minh cùng AI')
@section('meta_description', 'Học Toán lớp 1–12 theo đúng chương trình: bài giảng, luyện tập chấm ngay, đề kiểm tra và AI giảng lại từng bước khi làm sai.')
@section('html_class', 'landing-snap')

@section('body')
    @include('public.partials.header')

    <main>
        @include('public.partials.hero')
        @include('public.partials.features')
        @include('public.partials.ai-tutor')
        @include('public.partials.role-nav')
        @include('public.partials.for-student')
        @include('public.partials.for-teacher')
        @include('public.partials.for-parent')
        @include('public.partials.grades')
        @include('public.partials.pricing')
        @include('public.partials.cta')
    </main>

    @include('public.partials.footer')

    @push('scripts')
        @vite('resources/js/pwa-install.js')
    @endpush
@endsection
