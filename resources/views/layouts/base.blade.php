<!DOCTYPE html>
<html lang="vi" class="@yield('html_class')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Nền tảng học Toán trực tuyến lớp 1–12 cùng AI Tutor.')">

    <title>@yield('title', config('app.name'))</title>

    {{--
        Thẻ chia sẻ mạng xã hội (Zalo, Facebook, Messenger). Trang nào muốn preview riêng thì
        khai báo @section('og_title') / @section('og_description') / @section('og_image');
        không khai báo thì lấy lại title + meta description của chính trang đó.
        Ảnh mặc định: public/og-cover.png (1200×630) — đổi ảnh thì thay đúng file này.
    --}}
    @php
        $ogTitle = trim($__env->yieldContent('og_title')) ?: trim($__env->yieldContent('title', config('app.name')));
        $ogDescription = trim($__env->yieldContent('og_description'))
            ?: trim($__env->yieldContent('meta_description', 'Nền tảng học Toán trực tuyến lớp 1–12 cùng AI Tutor.'));
        $ogImage = trim($__env->yieldContent('og_image')) ?: asset('og-cover.png');
    @endphp

    <meta name="robots" content="@yield('robots', 'index,follow')">
    <link rel="canonical" href="{{ url()->current() }}">

    <meta property="og:site_name" content="{{ config('app.name') }}">
    <meta property="og:locale" content="vi_VN">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:title" content="{{ $ogTitle }}">
    <meta property="og:description" content="{{ $ogDescription }}">
    <meta property="og:image" content="{{ $ogImage }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">

    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="{{ $ogTitle }}">
    <meta name="twitter:description" content="{{ $ogDescription }}">
    <meta name="twitter:image" content="{{ $ogImage }}">

    {{-- PWA --}}
    <link rel="manifest" href="/manifest.webmanifest">
    <meta name="theme-color" content="#2563eb">
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="TOÁN AI">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Be+Vietnam+Pro:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('head')
</head>
<body>
    {{--
        Đang bảo trì mà vẫn xem được trang này nghĩa là trình duyệt đang giữ cookie bỏ qua.
        Không báo rõ thì người vừa bật bảo trì dễ tưởng là bật hụt, rồi bật thêm lần nữa.
        Style viết thẳng vào thẻ: dải này hiếm khi xuất hiện, không đáng thêm vào bundle CSS.
    --}}
    @if (app()->isDownForMaintenance())
        {{-- Nổi ở góc dưới bên trái: header trang chủ là sticky, sidebar và bottom nav thì fixed —
             một dải ngang ở đỉnh sẽ đè lên chúng. Góc này trống ở cả 4 portal lẫn trang công khai. --}}
        <style>
            .maintenance-flag {
                position: fixed; left: 12px; bottom: 12px; z-index: 2000; max-width: 300px;
                background: #b45309; color: #fff; border-radius: 10px; padding: .6rem .8rem;
                font-size: .78rem; line-height: 1.45; box-shadow: 0 6px 20px rgba(0, 0, 0, .25);
            }
            .maintenance-flag a { color: #fff; font-weight: 600; }
            @media (max-width: 991.98px) { .maintenance-flag { bottom: 76px; } }
        </style>
        <div class="maintenance-flag">
            <strong>Site đang bảo trì.</strong>
            Mọi người khác — kể cả ở trang chủ — đang thấy trang "Hệ thống đang được nâng cấp".
            Bạn vào được là nhờ quyền bỏ qua.
            @auth
                @if (auth()->user()->isAdmin())
                    <a href="{{ route('admin.maintenance.edit') }}">Tắt bảo trì</a>
                @endif
            @endauth
        </div>
    @endif

    @yield('body')

    @stack('widgets')
    @stack('scripts')
</body>
</html>
