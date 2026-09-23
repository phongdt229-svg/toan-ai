<?php

namespace App\Notifications;

use App\Models\Assignment;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Bài giao sắp tới hạn mà học sinh chưa nộp.
 *
 * CỐ Ý không có kênh mail: bài giao ngày nào cũng có, gửi email là biến hộp thư thành rác
 * và học sinh cũng không mở email. Chuông trong app + đẩy về điện thoại là đủ.
 */
class AssignmentDueSoon extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Assignment $assignment,
        public readonly int $hoursLeft,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? [] : ['database', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Bài giao sắp hết hạn',
            'message' => $this->line(),
            'url' => route('student.assignments.index'),
            'icon' => 'bi-alarm',
        ];
    }

    /** @return array<string, string> */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'Bài giao sắp hết hạn',
            'body' => $this->line(),
            'url' => route('student.assignments.index'),
        ];
    }

    private function line(): string
    {
        $when = $this->hoursLeft <= 1
            ? 'trong vòng một giờ nữa'
            : "sau {$this->hoursLeft} giờ nữa ({$this->assignment->due_at->format('H:i d/m')})";

        return "\"{$this->assignment->title}\" hết hạn {$when} mà em chưa nộp.";
    }
}
