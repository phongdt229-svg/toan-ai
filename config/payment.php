<?php

return [

    /*
    | momo = gọi cổng thật (sandbox hoặc production theo *_ENDPOINT).
    | fake = trang giả lập thanh toán ở local cho MỌI cổng, dùng đúng thuật toán ký của từng cổng để đi qua
    |        luồng IPN thật. Bị chặn ở production.
    | (Tên biến giữ nguyên từ hồi chỉ có MoMo để không phải sửa .env cũ.)
    */
    'gateway' => env('PAYMENT_GATEWAY', 'momo'),

    /*
    | Các cổng người dùng được chọn ở trang thanh toán, theo thứ tự hiển thị: "momo,vnpay".
    | Bỏ một cổng khỏi danh sách chỉ ẩn nút thanh toán — đơn cũ của cổng đó vẫn đối soát / hoàn tiền được.
    */
    'methods' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAYMENT_METHODS', 'momo'))))),

    // Khi PAYMENT_GATEWAY=fake: các cổng liệt kê ở đây vẫn gọi sandbox/production thật ("vnpay").
    'live' => array_values(array_filter(array_map('trim', explode(',', (string) env('PAYMENT_LIVE_GATEWAYS', ''))))),

    // Đơn chờ thanh toán quá thời gian này → huỷ (link thanh toán của cổng cũng tự hết hạn).
    'pending_expire_minutes' => (int) env('PAYMENT_PENDING_EXPIRE_MINUTES', 30),

    'momo' => [
        'partner_code' => env('MOMO_PARTNER_CODE'),
        'access_key' => env('MOMO_ACCESS_KEY'),
        'secret_key' => env('MOMO_SECRET_KEY'),
        // Chỉ host, không kèm path: https://test-payment.momo.vn | https://payment.momo.vn
        'endpoint' => rtrim((string) env('MOMO_ENDPOINT', 'https://test-payment.momo.vn'), '/'),
        'ipn_url' => env('MOMO_IPN_URL'),
        'return_url' => env('MOMO_RETURN_URL'),
        'request_type' => env('MOMO_REQUEST_TYPE', 'captureWallet'),
        'timeout' => 30,
    ],

    /*
    | VNPAY 2.1.0. IPN URL KHÔNG khai ở đây: VNPAY chỉ gọi URL đã đăng ký trong trang quản trị merchant
    | (https://<domain>/api/v1/payment/vnpay/ipn) — gửi kèm request cũng không có tác dụng.
    */
    'vnpay' => [
        'tmn_code' => env('VNPAY_TMN_CODE'),
        'hash_secret' => env('VNPAY_HASH_SECRET'),
        'pay_url' => env('VNPAY_PAY_URL', 'https://sandbox.vnpayment.vn/paymentv2/vpcpay.html'),
        'api_url' => env('VNPAY_API_URL', 'https://sandbox.vnpayment.vn/merchant_webapi/api/transaction'),
        'return_url' => env('VNPAY_RETURN_URL'),
        'timeout' => 30,
    ],

];
