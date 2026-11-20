<?php

/*
 * Danh mục huy hiệu. `rule`:
 *   ['count', <lý do trong reward_points>, N] — đã được cộng điểm vì lý do đó ít nhất N lần
 *   ['streak', N]                             — kỷ lục chuỗi ngày học ≥ N (StreakService)
 *   ['points', N]                             — tổng điểm ≥ N
 * Thêm huy hiệu = thêm một phần tử; BadgeService tự trao ở lần cộng điểm kế tiếp.
 * Đổi khoá của huy hiệu đã trao là mất huy hiệu của học sinh — chỉ thêm, đừng đổi tên khoá.
 * Icon viết ĐỦ tên class (bi-…) để bản build Bootstrap Icons giữ lại.
 */
return [
    'first_lesson' => ['name' => 'Bước đầu tiên', 'description' => 'Học xong bài học đầu tiên', 'icon' => 'bi-book', 'rule' => ['count', 'lesson_completed', 1]],
    'lessons_10' => ['name' => 'Chăm chỉ', 'description' => 'Học xong 10 bài học', 'icon' => 'bi-journal-check', 'rule' => ['count', 'lesson_completed', 10]],
    'lessons_50' => ['name' => 'Mọt sách', 'description' => 'Học xong 50 bài học', 'icon' => 'bi-journals', 'rule' => ['count', 'lesson_completed', 50]],
    'path_5' => ['name' => 'Đúng lộ trình', 'description' => 'Hoàn thành 5 buổi trong lộ trình', 'icon' => 'bi-signpost-split', 'rule' => ['count', 'path_session_done', 5]],
    'path_20' => ['name' => 'Bền bỉ', 'description' => 'Hoàn thành 20 buổi trong lộ trình', 'icon' => 'bi-flag', 'rule' => ['count', 'path_session_done', 20]],
    'exam_ace' => ['name' => 'Điểm giỏi', 'description' => 'Đạt từ 80% ở một đề kiểm tra', 'icon' => 'bi-trophy', 'rule' => ['count', 'exam_high_score', 1]],
    'exam_ace_10' => ['name' => 'Cao thủ', 'description' => 'Đạt từ 80% ở 10 đề kiểm tra', 'icon' => 'bi-award', 'rule' => ['count', 'exam_high_score', 10]],
    'streak_7' => ['name' => 'Một tuần không nghỉ', 'description' => 'Học 7 ngày liên tiếp', 'icon' => 'bi-fire', 'rule' => ['streak', 7]],
    'streak_30' => ['name' => 'Ngọn lửa 30 ngày', 'description' => 'Học 30 ngày liên tiếp', 'icon' => 'bi-lightning-charge', 'rule' => ['streak', 30]],
    'points_500' => ['name' => '500 điểm', 'description' => 'Tích luỹ 500 điểm thưởng', 'icon' => 'bi-gem', 'rule' => ['points', 500]],
    'points_2000' => ['name' => '2.000 điểm', 'description' => 'Tích luỹ 2.000 điểm thưởng', 'icon' => 'bi-stars', 'rule' => ['points', 2000]],
];
