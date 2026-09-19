<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Tài khoản giáo viên vừa được admin duyệt (§1). */
class TeacherAccountApproved extends Notification implements ShouldQueue
{
    use Queueable;

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Tài khoản đã được duyệt',
            'message' => 'Tài khoản giáo viên của bạn đã được duyệt — bắt đầu soạn bài và tạo lớp ngay.',
            'url' => route('teacher.dashboard'),
            'icon' => 'bi-patch-check',
        ];
    }
}
