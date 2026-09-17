{{--
    Layout chung cho 4 portal. Biến `portal` quyết định menu (config/navigation.php).
    Mobile: bottom nav · Desktop (≥ lg): sidebar. (§3)
--}}
@extends('layouts.base')

@php
    $items = config("navigation.{$portal}", []);
    $bottomItems = collect($items)->where('bottom', true)->take(4);
@endphp

@section('body')
    @include('components.sidebar', ['items' => $items, 'portal' => $portal])

    <div class="app-shell">
        <header class="navbar bg-white border-bottom sticky-top px-3">
            <div class="d-flex align-items-center gap-2">
                <span class="d-lg-none fw-bold text-primary">
                    <i class="bi bi-calculator me-1"></i>TOÁN AI
                </span>
                <h1 class="h6 mb-0 d-none d-lg-block">@yield('page_title', 'Trang chủ')</h1>
            </div>

            <div class="dropdown ms-auto">
                <button class="btn btn-sm btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                    <i class="bi bi-person-circle"></i>
                    <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
                    <li><span class="dropdown-item-text small text-secondary">{{ auth()->user()->email }}</span></li>
                    <li><hr class="dropdown-divider"></li>
                    <li>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="dropdown-item text-danger">
                                <i class="bi bi-box-arrow-right me-2"></i>Đăng xuất
                            </button>
                        </form>
                    </li>
                </ul>
            </div>
        </header>

        <main class="container-fluid px-3 py-3 py-lg-4">
            @include('components.flash')
            @yield('content')
        </main>
    </div>

    {{-- AI Tutor: chỉ cho học sinh, và ẩn hẳn trên trang làm bài kiểm tra (server cũng chặn). --}}
    @if ($portal === 'student' && ! request()->routeIs('student.exams.take'))
        @include('components.ai-tutor-panel')
    @endif

    <nav class="bottom-nav">
        @foreach ($bottomItems as $item)
            @include('components.nav-item', ['item' => $item, 'style' => 'bottom'])
        @endforeach
    </nav>
@endsection
