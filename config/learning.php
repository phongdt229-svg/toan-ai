<?php

/*
| Thông số học tập do người vận hành chọn — đổi trong .env, không sửa code.
*/
return [

    /*
    | D-02: phạm vi lớp hệ thống nhận học sinh. Đặc tả gốc ghi cả "6 → 12" (tổng quan) lẫn "1 → 12" (form đăng ký);
    | mặc định giữ 1 → 12 như hệ thống đang chạy. Đặt GRADE_MIN=6 thì lớp 1–5 biến mất khỏi đăng ký, chọn lớp,
    | trang chủ… (Grade::active() lọc theo khoảng này) — nội dung đã soạn vẫn nằm trong DB, mở lại được.
    */
    'grade_min' => max(1, (int) env('GRADE_MIN', 1)),
    'grade_max' => min(12, (int) env('GRADE_MAX', 12)),

    // D-04: một ngày được tính vào chuỗi ngày học khi học thực ≥ chừng này phút.
    'streak_min_minutes' => max(1, (int) env('STREAK_MIN_MINUTES', 10)),

];
