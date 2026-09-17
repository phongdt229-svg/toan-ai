<?php

/*
|--------------------------------------------------------------------------
| Menu theo role
|--------------------------------------------------------------------------
| `bottom` = true → hiện ở bottom nav trên mobile (§3, tối đa 4 mục).
| `route` = null  → chưa làm ở phase hiện tại, render dạng disabled.
*/

return [

    'student' => [
        ['label' => 'Trang chủ', 'icon' => 'bi-house', 'route' => 'student.dashboard', 'bottom' => true],
        ['label' => 'Học', 'icon' => 'bi-journal-text', 'route' => 'student.learn.index', 'bottom' => true],
        ['label' => 'Bài tập', 'icon' => 'bi-pencil-square', 'route' => 'student.practice.index', 'bottom' => true],
        ['label' => 'AI', 'icon' => 'bi-robot', 'route' => null, 'bottom' => true],
        ['label' => 'Đề kiểm tra', 'icon' => 'bi-clipboard-check', 'route' => null, 'bottom' => false],
        ['label' => 'Tiến độ', 'icon' => 'bi-graph-up', 'route' => null, 'bottom' => false],
    ],

    'teacher' => [
        ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'teacher.dashboard', 'bottom' => true],
        ['label' => 'Lớp học', 'icon' => 'bi-people', 'route' => null, 'bottom' => true],
        ['label' => 'Bài học', 'icon' => 'bi-journal-text', 'route' => 'teacher.lessons.index', 'bottom' => true],
        ['label' => 'Câu hỏi', 'icon' => 'bi-question-circle', 'route' => 'teacher.questions.index', 'bottom' => true],
        ['label' => 'Đề kiểm tra', 'icon' => 'bi-clipboard-check', 'route' => null, 'bottom' => false],
        ['label' => 'Bài tập', 'icon' => 'bi-pencil-square', 'route' => null, 'bottom' => false],
        ['label' => 'Học sinh', 'icon' => 'bi-mortarboard', 'route' => null, 'bottom' => false],
        ['label' => 'Báo cáo', 'icon' => 'bi-bar-chart', 'route' => null, 'bottom' => false],
    ],

    'parent' => [
        ['label' => 'Tổng quan', 'icon' => 'bi-house', 'route' => 'parent.dashboard', 'bottom' => true],
        ['label' => 'Con của tôi', 'icon' => 'bi-people', 'route' => null, 'bottom' => true],
        ['label' => 'Báo cáo', 'icon' => 'bi-bar-chart', 'route' => null, 'bottom' => true],
        ['label' => 'Gói học', 'icon' => 'bi-gem', 'route' => null, 'bottom' => true],
    ],

    'admin' => [
        ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'admin.dashboard', 'bottom' => true],
        ['label' => 'Duyệt giáo viên', 'icon' => 'bi-person-check', 'route' => 'admin.teachers.pending', 'bottom' => true],
        ['label' => 'Người dùng', 'icon' => 'bi-people', 'route' => null, 'bottom' => true],
        ['label' => 'Chương trình', 'icon' => 'bi-diagram-3', 'route' => 'admin.curriculum.index', 'bottom' => true],
        ['label' => 'Gói học', 'icon' => 'bi-gem', 'route' => null, 'bottom' => false],
        ['label' => 'Giao dịch', 'icon' => 'bi-credit-card', 'route' => null, 'bottom' => false],
        ['label' => 'AI usage', 'icon' => 'bi-robot', 'route' => null, 'bottom' => false],
        ['label' => 'Audit log', 'icon' => 'bi-shield-check', 'route' => null, 'bottom' => false],
    ],

];
