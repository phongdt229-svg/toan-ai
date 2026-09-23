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

// Dọn trạng thái gói: quá hạn → expired, chờ thanh toán bỏ dở > 24h → cancelled (§19).
// Quyền truy cập vốn dựa vào ends_at nên job chạy trễ cũng không cho dùng lố hạn.
// 08:00 chu khong gop vao subscriptions:expire luc 00:05 - khong ai muon nhan email gia han luc nua dem.
Schedule::command('subscriptions:remind-expiring')
    ->dailyAt('08:00')
    ->withoutOverlapping();

Schedule::command('subscriptions:expire')
    ->dailyAt('00:05')
    ->withoutOverlapping();

// Đơn MoMo quá hạn chưa trả: hỏi lại MoMo rồi mới huỷ (không huỷ nhầm đơn IPN bị lạc).
Schedule::command('payments:expire-pending')
    ->everyFiveMinutes()
    ->withoutOverlapping();

// Báo cáo tuần cho phụ huynh — tối Chủ nhật, lúc phụ huynh thường ngồi xem lại tuần học của con (§14).
Schedule::command('reports:weekly-parents')
    ->weeklyOn(0, '19:00')
    ->withoutOverlapping();

// Sao lưu DB lúc ít người học nhất; giữ BACKUP_KEEP_DAYS ngày. Chỉ chạy trên máy chủ thật.
Schedule::command('backup:database')
    ->dailyAt('02:00')
    ->environments(['production', 'staging'])
    ->withoutOverlapping()
    ->onOneServer();

// Dọn job lỗi cũ và token Sanctum hết hạn để bảng không phình.
Schedule::command('queue:prune-failed --hours=720')->weekly();
Schedule::command('sanctum:prune-expired --hours=168')->daily();

// Tài khoản đã yêu cầu xoá quá 30 ngày → ẩn danh vĩnh viễn (cam kết ở Chính sách bảo mật).
Schedule::command('accounts:purge')
    ->dailyAt('03:00')
    ->withoutOverlapping();
