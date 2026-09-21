{{--
    Khung chung cho trang lỗi. CỐ Ý không dùng @vite và không đọc session/DB:
    trang lỗi phải hiện được đúng lúc hệ thống đang hỏng (thiếu manifest, mất DB, đang deploy).
    Mọi CSS nội tuyến, mọi link viết cứng — không gọi route() để `php artisan down --render` cũng dùng được.
--}}
<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>@yield('title') — {{ config('app.name') }}</title>
    <link rel="icon" href="/icons/icon-192.png">
    <meta name="theme-color" content="#2563eb">
    <style>
        *, *::before, *::after { box-sizing: border-box; }
        body {
            margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center;
            padding: 24px; background: #f8fafc; color: #0f172a;
            font-family: "Be Vietnam Pro", system-ui, -apple-system, "Segoe UI", Roboto, sans-serif;
            line-height: 1.6;
        }
        .box { width: 100%; max-width: 560px; text-align: center; }
        .code {
            display: inline-block; font-size: 13px; font-weight: 700; letter-spacing: .08em;
            color: #2563eb; background: #e0ecff; border-radius: 999px; padding: 4px 14px; margin-bottom: 18px;
        }
        .art { font-size: 56px; line-height: 1; margin-bottom: 16px; }
        h1 { font-size: 26px; font-weight: 700; margin: 0 0 10px; }
        p { margin: 0 0 12px; color: #475569; }
        .actions { display: flex; flex-wrap: wrap; gap: 10px; justify-content: center; margin-top: 22px; }
        a.btn {
            display: inline-block; text-decoration: none; font-weight: 600; font-size: 15px;
            padding: 10px 20px; border-radius: 10px; border: 1px solid transparent;
        }
        a.primary { background: #2563eb; color: #fff; }
        a.primary:hover { background: #1d4ed8; }
        a.ghost { border-color: #cbd5e1; color: #334155; background: #fff; }
        a.ghost:hover { border-color: #94a3b8; }
        .foot { margin-top: 26px; font-size: 13px; color: #94a3b8; }
        .foot a { color: #64748b; }
        @media (prefers-color-scheme: dark) {
            body { background: #0b1220; color: #e2e8f0; }
            .code { background: #1e293b; color: #93c5fd; }
            p { color: #94a3b8; }
            a.ghost { background: transparent; border-color: #334155; color: #cbd5e1; }
        }
    </style>
</head>
<body>
    <div class="box">
        <div class="art">@yield('art', '🤔')</div>
        <div class="code">@yield('code')</div>
        <h1>@yield('title')</h1>
        @yield('message')

        <div class="actions">
            @section('actions')
                <a class="btn primary" href="/">Về trang chủ</a>
                <a class="btn ghost" href="/huong-dan">Hướng dẫn sử dụng</a>
            @show
        </div>

        <div class="foot">
            @section('foot')
                Cần trợ giúp? <a href="/ho-tro">Gửi yêu cầu hỗ trợ</a>
                hoặc email <a href="mailto:{{ config('site.email') }}">{{ config('site.email') }}</a>
            @show
        </div>
    </div>
</body>
</html>
