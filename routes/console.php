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
