<!DOCTYPE html>
<html lang="vi" class="@yield('html_class')">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="description" content="@yield('meta_description', 'Nền tảng học Toán trực tuyến lớp 1–12 cùng AI Tutor.')">

    @php
        // Chỉ nạp công cụ đo lường khi người dùng đã bấm "Đồng ý". Chặn ngay từ server:
        // nạp script rồi mới gọi API tắt là muộn, cookie của Google đã đặt xong từ trước đó.
        $analyticsAllowed = request()->cookie(\App\Http\Controllers\Web\CookieConsentController::COOKIE)
            === \App\Http\Controllers\Web\CookieConsentController::ACCEPTED;
    @endphp

    {{-- Google Tag Manager — Google yêu cầu đặt càng cao trong <head> càng tốt. Trống ở local/test. --}}
    @if ($analyticsAllowed && config('site.google_tag_manager_id'))
        <script>
            (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':
            new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],
            j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src=
            'https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);
            })(window,document,'script','dataLayer',@json(config('site.google_tag_manager_id')));
        </script>
    @endif

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

    @if (config('site.google_site_verification'))
        <meta name="google-site-verification" content="{{ config('site.google_site_verification') }}">
    @endif

    @vite(['resources/scss/app.scss', 'resources/js/app.js'])
    @stack('head')

    {{--
        Google Analytics 4. Trống ở local/test (xem config/site.php) nên dev không làm bẩn số liệu.
        Đặt CUỐI <head>: script `async` nhưng vẫn là một lượt tải thêm, không để nó chen trước CSS.
        CSP hiện không khai script-src nên không phải mở thêm nguồn — xem SecurityHeaders.
    --}}
    @if ($analyticsAllowed && config('site.google_analytics_id'))
        @php $gaId = config('site.google_analytics_id'); @endphp
        <script async src="https://www.googletagmanager.com/gtag/js?id={{ $gaId }}"></script>
        <script>
            window.dataLayer = window.dataLayer || [];
            function gtag(){dataLayer.push(arguments);}
            gtag('js', new Date());
            gtag('config', @json($gaId));
        </script>
    @endif
</head>
<body>
    {{-- GTM bản dự phòng cho trình duyệt tắt JS — phải nằm ngay sau <body>. --}}
    @if ($analyticsAllowed && config('site.google_tag_manager_id'))
        <noscript>
            <iframe src="https://www.googletagmanager.com/ns.html?id={{ config('site.google_tag_manager_id') }}"
                    height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe>
        </noscript>
    @endif

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

    @include('components.cookie-consent')

    @stack('widgets')
    @stack('scripts')
</body>
</html>
