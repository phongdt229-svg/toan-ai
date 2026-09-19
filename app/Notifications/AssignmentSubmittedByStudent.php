<?php

namespace App\Notifications;

use App\Models\Assignment;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Giáo viên: có học sinh vừa nộp/hoàn thành một bài đã giao. */
class AssignmentSubmittedByStudent extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly Assignment $assignment,
        public readonly User $student,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Học sinh vừa nộp bài',
            'message' => "{$this->student->name} vừa hoàn thành bài giao \"{$this->assignment->title}\".",
            'url' => route('teacher.assignments.show', $this->assignment),
            'icon' => 'bi-send-check',
        ];
    }
}
