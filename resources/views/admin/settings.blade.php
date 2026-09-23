@extends('layouts.app', ['portal' => 'admin'])

@section('title', 'Cài đặt — TOÁN AI')
@section('page_title', 'Cài đặt')

@section('content')
    {{-- Hai cột từ lg: hồ sơ + email bên trái, thông báo + mật khẩu bên phải — khỏi cuộn dài. --}}
    <div class="row g-3">
        <div class="col-12 col-lg-6">
            @include('partials.profile-form', ['profileRoute' => route('admin.settings.profile')])
            @include('partials.email-form')
        </div>
        <div class="col-12 col-lg-6">
            @include('partials.notification-preferences-form')
            @include('partials.password-form', ['passwordRoute' => route('admin.settings.password')])
            @include('partials.two-factor-form')
            @include('partials.data-export')
        </div>
    </div>
@endsection
