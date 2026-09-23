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

    /*
    | Link mạng xã hội hiện ở footer. Để trống thì ẩn đúng biểu tượng đó — KHÔNG bịa link,
    | dán đúng URL trang thật trước khi chạy production. Trang không tồn tại còn hại hơn là không có link.
    |
    | Khác với `facebook_url` ở trên: cái đó là link CHAT (m.me/...) cho nút nổi,
    | còn `social.facebook` là link TRANG (facebook.com/...).
    |
    | 'google' dùng cho hồ sơ Google Doanh nghiệp / Google Maps, không phải trang mạng xã hội —
    | đặt ở đây cho gọn vì chỗ hiển thị là một.
    */
    'social' => [
        'facebook' => env('SITE_SOCIAL_FACEBOOK', ''),   // https://facebook.com/<trang>
        'youtube' => env('SITE_SOCIAL_YOUTUBE', ''),     // https://youtube.com/@<kênh>
        'tiktok' => env('SITE_SOCIAL_TIKTOK', ''),       // https://tiktok.com/@<tài khoản>
        'x' => env('SITE_SOCIAL_X', ''),                 // https://x.com/<tài khoản>
        'google' => env('SITE_SOCIAL_GOOGLE', ''),       // link hồ sơ Google Doanh nghiệp
    ],

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

    // Link nhúng báo cáo Looker Studio (Chia sẻ → Nhúng báo cáo → URL nhúng) hiện ở Quản trị → Google Analytics.
    // Chỉ nhận https://lookerstudio.google.com/... — xem AnalyticsController. Trống = không hiện khung.
    'looker_studio_embed_url' => env('LOOKER_STUDIO_EMBED_URL', ''),

    // Ngày cập nhật hai trang pháp lý — sửa nội dung thì sửa luôn ngày này.
    'legal_updated_at' => env('SITE_LEGAL_UPDATED_AT', '24/09/2026'),

];
