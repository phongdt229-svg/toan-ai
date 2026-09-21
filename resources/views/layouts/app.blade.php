{{--
    Layout chung cho 4 portal. Biến `portal` quyết định menu (config/navigation.php).
    Mobile: bottom nav · Desktop (≥ lg): sidebar. (§3)
--}}
@extends('layouts.base')

{{-- Khu vực làm việc sau đăng nhập: không để công cụ tìm kiếm lập chỉ mục. --}}
@section('robots', 'noindex,nofollow')

@php
    $items = config("navigation.{$portal}", []);
    $bottomItems = collect($items)->where('bottom', true)->take(4);

    $recentNotifications = auth()->user()->notifications()->latest()->take(8)->get();
    $unreadNotificationCount = auth()->user()->unreadNotifications()->count();
@endphp

@section('body')
    @include('components.sidebar', ['items' => $items, 'portal' => $portal])
    @include('components.mobile-menu', ['items' => $items, 'portal' => $portal])

    <div class="app-shell">
        <header class="navbar bg-white border-bottom sticky-top px-3">
            <div class="d-flex align-items-center gap-2">
                {{-- Mobile: mở menu đầy đủ; desktop đã có sidebar nên ẩn nút này. --}}
                <button class="btn btn-sm btn-light d-lg-none" type="button" data-bs-toggle="offcanvas"
                        data-bs-target="#appMenu" aria-controls="appMenu" aria-label="Mở menu">
                    <i class="bi bi-list fs-5"></i>
                </button>
                <x-brand class="d-lg-none" size="sm" />
                <h1 class="h6 mb-0 d-none d-lg-block">@yield('page_title', 'Trang chủ')</h1>
            </div>

            @if ($portal === 'teacher')
                {{-- Tìm bài học/câu hỏi/học sinh từ bất kỳ đâu trong portal — chỉ hiện ở màn desktop, mobile vào mục "Tìm kiếm" trong menu. --}}
                <form method="GET" action="{{ route('teacher.search') }}" class="d-none d-lg-flex ms-3" style="width:280px">
                    <input type="search" name="q" class="form-control form-control-sm" placeholder="Tìm bài học, câu hỏi, học sinh...">
                </form>
            @endif

            <div class="d-flex align-items-center gap-2 ms-auto">
                <div class="dropdown">
                    <button class="btn btn-sm btn-light position-relative" data-bs-toggle="dropdown" aria-label="Thông báo">
                        <i class="bi bi-bell"></i>
                        @if ($unreadNotificationCount > 0)
                            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.625rem">
                                {{ $unreadNotificationCount > 9 ? '9+' : $unreadNotificationCount }}
                                <span class="visually-hidden">thông báo chưa đọc</span>
                            </span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end p-0" style="width:320px;max-width:88vw">
                        <div class="d-flex align-items-center justify-content-between px-3 py-2 border-bottom">
                            <span class="fw-semibold small">Thông báo</span>
                            @if ($unreadNotificationCount > 0)
                                <form method="POST" action="{{ route('notifications.read_all') }}">
                                    @csrf
                                    <button class="btn btn-link btn-sm p-0 small">Đánh dấu đã đọc</button>
                                </form>
                            @endif
                        </div>
                        <div style="max-height:360px;overflow-y:auto">
                            @forelse ($recentNotifications as $n)
                                <a href="{{ route('notifications.open', $n) }}"
                                   class="dropdown-item py-2 border-bottom {{ $n->read_at ? '' : 'bg-light' }}"
                                   style="white-space:normal">
                                    <div class="d-flex gap-2">
                                        <i class="bi {{ $n->data['icon'] ?? 'bi-bell' }} text-primary mt-1"></i>
                                        <div>
                                            <div class="small fw-semibold">{{ $n->data['title'] }}</div>
                                            <div class="small text-secondary">{{ $n->data['message'] }}</div>
                                            <div class="text-secondary" style="font-size:.7rem">{{ $n->created_at->diffForHumans() }}</div>
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="text-secondary small text-center py-4">Chưa có thông báo nào.</div>
                            @endforelse
                        </div>
                        <a href="{{ route('notifications.index') }}" class="dropdown-item text-center small py-2 border-top">Xem tất cả</a>
                    </div>
                </div>

                <div class="dropdown">
                    <button class="btn btn-sm btn-light d-flex align-items-center gap-2" data-bs-toggle="dropdown">
                        <i class="bi bi-person-circle"></i>
                        <span class="d-none d-sm-inline">{{ auth()->user()->name }}</span>
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li><span class="dropdown-item-text small text-secondary">{{ auth()->user()->email }}</span></li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <a class="dropdown-item" href="{{ route('guides.index', ['doi-tuong' => $portal === 'admin' ? 'all' : $portal]) }}">
                                <i class="bi bi-book me-2"></i>Hướng dẫn sử dụng
                            </a>
                        </li>
                        <li>
                            <a class="dropdown-item" href="{{ route('support.create') }}">
                                <i class="bi bi-life-preserver me-2"></i>Gửi yêu cầu hỗ trợ
                            </a>
                        </li>
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
            </div>
        </header>

        <main class="container-fluid px-3 py-3 py-lg-4">
            @include('components.flash')

            {{-- Nhắc xác thực email: không chặn việc học, chỉ chặn mua gói (xem route packages.*). --}}
            @if (! auth()->user()->hasVerifiedEmail())
                <div class="alert alert-warning d-flex flex-wrap align-items-center gap-2">
                    <i class="bi bi-envelope-exclamation"></i>
                    <span class="flex-grow-1 small">
                        Email <strong>{{ auth()->user()->email }}</strong> chưa được xác thực — bạn sẽ không nhận được
                        thư đặt lại mật khẩu, báo cáo học tập hay biên nhận thanh toán.
                    </span>
                    <form method="POST" action="{{ route('verification.send') }}">
                        @csrf
                        <button class="btn btn-sm btn-warning">Gửi lại email xác thực</button>
                    </form>
                </div>
            @endif
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
