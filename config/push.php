<?php

/*
| Web Push (VAPID). CHƯA khai khoá thì tính năng tắt hẳn: không hiện công tắc trong Cài đặt,
| không gửi gì cả — cùng khuôn với Google Analytics.
|
| Sinh khoá một lần rồi dán vào .env (KHÔNG commit khoá riêng):
|   php artisan push:keys
*/

return [

    'public_key' => env('VAPID_PUBLIC_KEY', ''),
    'private_key' => env('VAPID_PRIVATE_KEY', ''),

    // Theo chuẩn VAPID phải là mailto: hoặc URL để dịch vụ đẩy liên hệ được khi có sự cố.
    'subject' => env('VAPID_SUBJECT', 'mailto:'.env('SITE_EMAIL', 'lienhe@toan-ai.vn')),

];
