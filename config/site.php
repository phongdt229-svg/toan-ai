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

    // Ngày cập nhật hai trang pháp lý — sửa nội dung thì sửa luôn ngày này.
    'legal_updated_at' => env('SITE_LEGAL_UPDATED_AT', '18/09/2026'),

];
