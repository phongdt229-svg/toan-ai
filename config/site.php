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

    // Ngày cập nhật hai trang pháp lý — sửa nội dung thì sửa luôn ngày này.
    'legal_updated_at' => env('SITE_LEGAL_UPDATED_AT', '18/09/2026'),

];
