<?php

/*
| Thông tin pháp lý / liên hệ hiện trên footer, trang Điều khoản và Chính sách bảo mật.
| ĐỂ TRỐNG hoặc giá trị mẫu ở local — trước khi chạy thật phải điền đúng thông tin đơn vị vận hành,
| vì đây là nội dung người dùng và cơ quan quản lý căn cứ vào.
*/

return [

    // Tên hiển thị trên logo. Đổi ở đây là đổi cả header, sidebar, footer.
    'brand' => env('SITE_BRAND', 'Math AI'),

    'company' => env('SITE_COMPANY', 'TOÁN AI'),
    'email' => env('SITE_EMAIL', 'lienhe@toan-ai.vn'),
    'hotline' => env('SITE_HOTLINE', ''),
    'address' => env('SITE_ADDRESS', ''),

    // Nút chat nổi ở các trang công khai (landing, gói học, hỗ trợ...). Để trống thì ẩn nút
    // tương ứng — KHÔNG bịa link, dán đúng URL Zalo/Facebook thật trước khi chạy production.
    // Zalo: https://zalo.me/<số điện thoại hoặc id OA> — lấy ở phần "Liên kết Zalo" của OA.
    // Facebook: https://m.me/<tên trang> — lấy ở phần cài đặt Messenger của trang.
    'zalo_url' => env('SITE_ZALO_URL', ''),
    'facebook_url' => env('SITE_FACEBOOK_URL', ''),

    // Số dòng mỗi trang ở khu quản trị (danh sách người dùng, giao dịch, hỗ trợ…).
    'per_page' => (int) env('SITE_PER_PAGE', 10),

    // Thời gian dự kiến hiện trên trang bảo trì khi bật bằng lệnh `php artisan down`.
    // Bật từ trang Quản trị thì lấy theo ô người bật tự nhập, không dùng giá trị này.
    'maintenance_eta' => env('DEPLOY_ETA', '15 phút'),

    /*
    | Google Analytics 4. Measurement ID là thông tin công khai (ai xem mã nguồn trang cũng thấy)
    | nên để thẳng trong repo, không phải loại phải giấu như key MoMo/OpenAI.
    |
    | Local và test để TRỐNG: lượt truy cập lúc dev không được làm bẩn số liệu thật.
    | Muốn thử ở local thì đặt GOOGLE_ANALYTICS_ID trong .env.
    |
    | Bật GA là có cookie phân tích của bên thứ ba → phải khai trong Chính sách bảo mật §3 và §8.
    */
    'google_analytics_id' => env('GOOGLE_ANALYTICS_ID', env('APP_ENV') === 'production' ? 'G-QJDV1HXSGX' : ''),

    /*
    | Google Tag Manager. CẨN THẬN: nếu trong container GTM bạn cũng cấu hình một thẻ GA4 cùng
    | Measurement ID với `google_analytics_id` ở trên thì mỗi lượt xem trang bị đếm HAI LẦN.
    | Chọn một trong hai đường: hoặc để gtag.js ở đây, hoặc bỏ trống ID GA4 và khai GA4 bên trong GTM.
    */
    'google_tag_manager_id' => env('GOOGLE_TAG_MANAGER_ID', env('APP_ENV') === 'production' ? 'GTM-TR8MK5R8' : ''),

    // Thẻ xác minh Google Search Console — vô hại ở mọi môi trường, Google cần thấy nó trên domain thật.
    'google_site_verification' => env('GOOGLE_SITE_VERIFICATION', 'GuZkEv4BKbqG8cgzsFVFdNseP-XJOYhO6ki-g7CZ9Xk'),

    // Ngày cập nhật hai trang pháp lý — sửa nội dung thì sửa luôn ngày này.
    'legal_updated_at' => env('SITE_LEGAL_UPDATED_AT', '23/09/2026'),

];
