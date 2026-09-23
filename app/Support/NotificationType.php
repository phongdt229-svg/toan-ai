<?php

namespace App\Support;

use App\Notifications\AssignmentSubmittedByStudent;
use App\Notifications\ChildScoreLow;
use App\Notifications\ExamResultReady;
use App\Notifications\PaymentSucceeded;
use App\Notifications\SubscriptionExpiringSoon;
use App\Notifications\SupportTicketResolved;
use App\Notifications\TeacherAccountApproved;
use App\Notifications\WeeklyReportReady;

/**
 * Nhãn tiếng Việt cho từng loại thông báo trong app — dùng ở trang cài đặt (bật/tắt theo loại)
 * và trong via() của từng Notification (User::hasMutedNotification()) để biết loại nào bị tắt.
 * Key = FQCN của class Notification, ổn định kể cả sau này đổi nội dung toArray().
 */
class NotificationType
{
    public const LABELS = [
        ExamResultReady::class => 'Có điểm bài kiểm tra',
        AssignmentSubmittedByStudent::class => 'Học sinh nộp bài giao',
        TeacherAccountApproved::class => 'Tài khoản được duyệt',
        ChildScoreLow::class => 'Con điểm thấp',
        WeeklyReportReady::class => 'Báo cáo tuần sẵn sàng',
        SupportTicketResolved::class => 'Yêu cầu hỗ trợ đã xử lý',
        PaymentSucceeded::class => 'Thanh toán thành công',
        SubscriptionExpiringSoon::class => 'Gói học sắp hết hạn',
    ];
}
