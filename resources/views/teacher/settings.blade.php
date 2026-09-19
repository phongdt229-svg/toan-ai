@extends('layouts.app', ['portal' => 'teacher'])

@section('title', 'Cài đặt — TOÁN AI')
@section('page_title', 'Cài đặt')

@section('content')
    <h2 class="h5 fw-bold mb-3">Cài đặt tài khoản</h2>

    @include('partials.profile-form', ['profileRoute' => route('teacher.settings.profile')])
    @include('partials.notification-preferences-form')
    @include('partials.password-form', ['passwordRoute' => route('teacher.settings.password')])
@endsection
