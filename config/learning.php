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

    /*
    | Công cụ vẽ hình gắn vào trang "Học" của lớp có học phần tương ứng (bỏ khỏi menu trái — yêu cầu 19/11).
    | Theo CT GDPT 2018: hình phẳng lớp 6–9 + vectơ/toạ độ lớp 10; hình khối trực quan lớp 8 (hình chóp đều),
    | lớp 9 (trụ, nón, cầu) và hình học không gian lớp 11–12.
    */
    'geometry_tools' => [
        'plane' => ['route' => 'student.plane-geometry.index', 'label' => 'Hình học phẳng', 'icon' => 'bi-triangle',
            'hint' => 'Vẽ điểm, đoạn, đường tròn, đo góc và độ dài', 'levels' => [6, 7, 8, 9, 10]],
        'solid' => ['route' => 'student.solid-geometry.index', 'label' => 'Hình học không gian', 'icon' => 'bi-box',
            'hint' => 'Dựng hình khối 3D, xoay, tính thể tích và diện tích', 'levels' => [8, 9, 11, 12]],
    ],

];
