<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Lịch chạy định kỳ
|--------------------------------------------------------------------------
| Local chạy bằng `php artisan schedule:work`.
| Production cần cron: * * * * * php artisan schedule:run
*/

// Lượt làm đề quá giờ mà không nộp → tự nộp (§16, PROJECT_PLAN Phase 4).
Schedule::command('exams:finalize-expired')
    ->everyMinute()
    ->withoutOverlapping();

// Báo cáo tuần cho phụ huynh — tối Chủ nhật, lúc phụ huynh thường ngồi xem lại tuần học của con (§14).
Schedule::command('reports:weekly-parents')
    ->weeklyOn(0, '19:00')
    ->withoutOverlapping();
