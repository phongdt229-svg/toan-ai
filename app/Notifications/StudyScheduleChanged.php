<?php

namespace App\Notifications;

use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Lịch học tuần bị sửa bởi bên kia (phụ huynh ↔ con). */
class StudyScheduleChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $student,
        public readonly User $editor,
        public readonly int $daysPerWeek,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? [] : ['database', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return ['title' => 'Lịch học thay đổi', 'message' => $this->line($notifiable), 'url' => $this->url($notifiable), 'icon' => 'bi-calendar-week'];
    }

    /** @return array<string, string> */
    public function toPush(object $notifiable): array
    {
        return ['title' => 'Lịch học thay đổi', 'body' => $this->line($notifiable), 'url' => $this->url($notifiable)];
    }

    private function line(object $notifiable): string
    {
        $days = $this->daysPerWeek > 0 ? "{$this->daysPerWeek} buổi/tuần" : 'không còn buổi nào';

        return $notifiable->is($this->student)
            ? "Phụ huynh {$this->editor->name} vừa cập nhật lịch học của em: {$days}."
            : "{$this->student->name} vừa cập nhật lịch học: {$days}.";
    }

    private function url(object $notifiable): string
    {
        return $notifiable->is($this->student)
            ? route('student.schedule.edit')
            : route('parent.children.schedule.edit', $this->student);
    }
}
