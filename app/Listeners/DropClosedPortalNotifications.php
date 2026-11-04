<?php

namespace App\Listeners;

use App\Models\User;
use App\Notifications\ChildAttendanceAlert;
use App\Notifications\ChildScoreLow;
use App\Notifications\DailyChildReport;
use App\Notifications\StudyScheduleChanged;
use App\Notifications\WeeklyReportReady;
use App\Support\RolePortal;
use Illuminate\Notifications\Events\NotificationSending;

/**
 * Cổng đang ẩn thì không gửi thông báo theo dõi: thư đó trỏ về trang người nhận không mở được.
 * Chỉ chặn đúng nhóm này — biên lai, hoàn tiền, đặt lại mật khẩu vẫn phải tới tay người dùng.
 */
class DropClosedPortalNotifications
{
    private const TRACKING = [
        ChildAttendanceAlert::class,
        ChildScoreLow::class,
        DailyChildReport::class,
        StudyScheduleChanged::class,
        WeeklyReportReady::class,
    ];

    public function handle(NotificationSending $event): ?bool
    {
        $blocked = $event->notifiable instanceof User
            && in_array($event->notification::class, self::TRACKING, true)
            && RolePortal::blocks($event->notifiable);

        return $blocked ? false : null;
    }
}
