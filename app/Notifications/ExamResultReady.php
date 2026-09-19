<?php

namespace App\Notifications;

use App\Models\ExamAttempt;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Học sinh vừa có điểm cuối cùng của một lượt làm đề (§10). */
class ExamResultReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly ExamAttempt $attempt) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? [] : ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $attempt = $this->attempt->loadMissing('exam');
        $percent = $attempt->percent();

        return [
            'title' => 'Có điểm bài kiểm tra',
            'message' => "{$attempt->exam->title}: {$attempt->score}/{$attempt->total_points} điểm".
                ($percent !== null ? " ({$percent}%)" : ''),
            'url' => route('student.exams.result', $attempt),
            'icon' => 'bi-clipboard-check',
        ];
    }
}
