<?php

return [

    /*
    | momo = gọi MoMo thật (sandbox hoặc production theo MOMO_ENDPOINT).
    | fake = trang giả lập thanh toán ở local, dùng đúng thuật toán ký của MoMo để đi qua luồng IPN thật.
    |        Bị chặn ở production.
    */
    'gateway' => env('PAYMENT_GATEWAY', 'momo'),

    // Đơn chờ thanh toán quá thời gian này → huỷ (MoMo payUrl cũng tự hết hạn).
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

];
