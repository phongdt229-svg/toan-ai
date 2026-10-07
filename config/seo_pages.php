<?php

/*
| Danh sách CỐ ĐỊNH các trang tĩnh công khai admin được sửa meta_description ở
| Quản trị → SEO (SeoPageController). Cố tình không cho nhập route_name tự do —
| gõ sai tên route là ghi đè nhầm trang, hoặc tạo override cho route không tồn tại
| mà không ai biết. Thêm trang tĩnh mới → thêm một dòng ở đây.
|
| KHÔNG đưa route của bài viết (`blog.show`)/hướng dẫn (`guides.show`) vào đây — hai
| loại đó đã có mô tả riêng theo từng bài (BlogPost::excerpt / config/guides.php),
| thêm override chung vào bảng này chỉ tạo ra hai nguồn sự thật xung đột nhau.
*/

return [
    'home' => 'Trang chủ',
    'register' => 'Chọn vai trò đăng ký',
    'login' => 'Đăng nhập',
    'register.student' => 'Đăng ký học sinh',
    'register.teacher' => 'Đăng ký giáo viên',
    'register.parent' => 'Đăng ký phụ huynh',
    'password.request' => 'Quên mật khẩu',
    'packages.index' => 'Gói học',
    'guides.index' => 'Trung tâm hướng dẫn',
    'support.create' => 'Hỗ trợ',
    'legal.terms' => 'Điều khoản sử dụng',
    'legal.privacy' => 'Chính sách bảo mật',
];
