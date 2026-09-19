<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Phụ huynh: báo cáo học tập tuần vừa gửi qua email — thông báo trong app song song (§14). */
class WeeklyReportReady extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<User>  $children  Các con đã có trong báo cáo tuần này. */
    public function __construct(public readonly array $children) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        $names = collect($this->children)->pluck('name')->implode(', ');

        return [
            'title' => 'Báo cáo tuần đã sẵn sàng',
            'message' => "Báo cáo học tập tuần của {$names} đã gửi qua email — bấm để xem chi tiết trong app.",
            'url' => count($this->children) === 1 ? route('parent.children.show', $this->children[0]) : route('parent.dashboard'),
            'icon' => 'bi-envelope-paper',
        ];
    }
}
