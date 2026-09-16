@extends('layouts.base')

@section('title', 'TOÁN AI — Học Toán thông minh cùng AI')

@section('body')
    @include('public.partials.header')

    <main>
        @include('public.partials.hero')
        @include('public.partials.features')
        @include('public.partials.ai-tutor')
        @include('public.partials.for-student')
        @include('public.partials.for-teacher')
        @include('public.partials.for-parent')
        @include('public.partials.grades')
        @include('public.partials.pricing')
        @include('public.partials.cta')
    </main>

    @include('public.partials.footer')
@endsection
