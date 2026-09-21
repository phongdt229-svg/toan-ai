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
        ['label' => 'AI', 'icon' => 'bi-robot', 'route' => 'student.ai.index', 'bottom' => true],
        ['label' => 'Đề kiểm tra', 'icon' => 'bi-clipboard-check', 'route' => 'student.exams.index', 'bottom' => false],
        ['label' => 'Bài được giao', 'icon' => 'bi-list-check', 'route' => 'student.assignments.index', 'bottom' => false],
        ['label' => 'Lớp của tôi', 'icon' => 'bi-people', 'route' => 'student.classes.index', 'bottom' => false],
        ['label' => 'Phụ huynh', 'icon' => 'bi-house-heart', 'route' => 'student.parents.index', 'bottom' => false],
        ['label' => 'Lộ trình', 'icon' => 'bi-signpost-split', 'route' => 'student.path.show', 'bottom' => false],
        ['label' => 'Gói của tôi', 'icon' => 'bi-gem', 'route' => 'student.subscription.index', 'bottom' => false],
        ['label' => 'Cài đặt', 'icon' => 'bi-gear', 'route' => 'student.settings', 'bottom' => false],
    ],

    'teacher' => [
        ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'teacher.dashboard', 'bottom' => true],
        ['label' => 'Lớp học', 'icon' => 'bi-people', 'route' => 'teacher.classes.index', 'bottom' => true],
        ['label' => 'Bài học', 'icon' => 'bi-journal-text', 'route' => 'teacher.lessons.index', 'bottom' => true],
        ['label' => 'Câu hỏi', 'icon' => 'bi-question-circle', 'route' => 'teacher.questions.index', 'bottom' => true],
        ['label' => 'Đề kiểm tra', 'icon' => 'bi-clipboard-check', 'route' => 'teacher.exams.index', 'bottom' => false],
        ['label' => 'Giao bài', 'icon' => 'bi-send-check', 'route' => 'teacher.assignments.index', 'bottom' => false],
        ['label' => 'Học sinh', 'icon' => 'bi-mortarboard', 'route' => 'teacher.students.index', 'bottom' => false],
        ['label' => 'AI soạn bài', 'icon' => 'bi-robot', 'route' => 'teacher.ai.index', 'bottom' => false],
        ['label' => 'Báo cáo', 'icon' => 'bi-bar-chart', 'route' => 'teacher.reports.index', 'bottom' => false],
        ['label' => 'Tìm kiếm', 'icon' => 'bi-search', 'route' => 'teacher.search', 'bottom' => false],
        ['label' => 'Cài đặt', 'icon' => 'bi-gear', 'route' => 'teacher.settings', 'bottom' => false],
    ],

    'parent' => [
        ['label' => 'Con của tôi', 'icon' => 'bi-house-heart', 'route' => 'parent.dashboard', 'bottom' => true],
        ['label' => 'Liên kết con', 'icon' => 'bi-person-plus', 'route' => 'parent.children.link', 'bottom' => true],
        ['label' => 'Gói học', 'icon' => 'bi-gem', 'route' => 'parent.subscriptions.index', 'bottom' => true],
        ['label' => 'Cài đặt', 'icon' => 'bi-gear', 'route' => 'parent.settings', 'bottom' => true],
    ],

    'admin' => [
        ['label' => 'Dashboard', 'icon' => 'bi-speedometer2', 'route' => 'admin.dashboard', 'bottom' => true],
        ['label' => 'Duyệt giáo viên', 'icon' => 'bi-person-check', 'route' => 'admin.teachers.pending', 'bottom' => true],
        ['label' => 'Người dùng', 'icon' => 'bi-people', 'route' => 'admin.users.index', 'bottom' => true],
        ['label' => 'Chương trình', 'icon' => 'bi-diagram-3', 'route' => 'admin.curriculum.index', 'bottom' => true],
        ['label' => 'Gói học', 'icon' => 'bi-gem', 'route' => 'admin.packages.index', 'bottom' => false],
        ['label' => 'Đăng ký gói', 'icon' => 'bi-person-vcard', 'route' => 'admin.subscriptions.index', 'bottom' => false],
        ['label' => 'Giao dịch', 'icon' => 'bi-credit-card', 'route' => 'admin.payments.index', 'bottom' => false],
        ['label' => 'AI usage', 'icon' => 'bi-robot', 'route' => 'admin.ai-usage.index', 'bottom' => false],
        ['label' => 'Hỗ trợ', 'icon' => 'bi-life-preserver', 'route' => 'admin.support.index', 'bottom' => false],
        ['label' => 'Audit log', 'icon' => 'bi-shield-check', 'route' => 'admin.audit-logs.index', 'bottom' => false],
        ['label' => 'Bảo trì', 'icon' => 'bi-cone-striped', 'route' => 'admin.maintenance.edit', 'bottom' => false],
        ['label' => 'Cài đặt', 'icon' => 'bi-gear', 'route' => 'admin.settings', 'bottom' => false],
    ],

];
