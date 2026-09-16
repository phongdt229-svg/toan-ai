@extends('layouts.base')

@section('body')
    <nav class="navbar navbar-expand-lg bg-white border-bottom sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold text-primary" href="{{ route('home') }}">
                <i class="bi bi-calculator me-1"></i>TOÁN AI
            </a>

            <div class="d-flex gap-2 ms-auto">
                @auth
                    <a href="{{ auth()->user()->homeRoute() }}" class="btn btn-primary btn-sm">Vào học</a>
                @else
                    <a href="{{ route('login') }}" class="btn btn-outline-primary btn-sm">Đăng nhập</a>
                    <a href="{{ route('register') }}" class="btn btn-primary btn-sm">Đăng ký</a>
                @endauth
            </div>
        </div>
    </nav>

    <main class="py-4 py-lg-5">
        <div class="container">
            <div class="row justify-content-center">
                <div class="col-12 col-sm-10 col-md-8 col-lg-6 col-xl-5">
                    @include('components.flash')
                    @yield('content')
                </div>
            </div>
        </div>
    </main>

    <footer class="border-top py-4 mt-auto">
        <div class="container text-center text-secondary small">
            © {{ date('Y') }} TOÁN AI — Học Toán thông minh cùng AI
        </div>
    </footer>
@endsection
