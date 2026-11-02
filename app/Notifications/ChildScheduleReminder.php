<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Nhắc phụ huynh lịch học hôm nay của con (đặc tả module 9: "Nhắc lịch học cho học sinh — hằng ngày").
 *
 * Một thông báo gom mọi con của phụ huynh. Không có kênh mail: ngày nào cũng gửi thì email là rác
 * (cùng lý do với StudyReminder). Thông tin để phụ huynh nhắc con đúng giờ, nên đẩy trước buổi học đầu tiên.
 */
class ChildScheduleReminder extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<array{name: string, slots: list<array{start: string, minutes: int}>}>  $children */
    public function __construct(public readonly array $children) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? [] : ['database', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Lịch học hôm nay của con',
            'message' => $this->line(),
            'url' => route('parent.dashboard'),
            'icon' => 'bi-calendar-week',
        ];
    }

    /** @return array<string, string> */
    public function toPush(object $notifiable): array
    {
        return ['title' => 'Lịch học hôm nay của con', 'body' => $this->line(), 'url' => route('parent.dashboard')];
    }

    /** "Nam học 19:30 (60 phút) · Mai học 18:00, 20:00." */
    private function line(): string
    {
        return collect($this->children)->map(function (array $child) {
            $slots = collect($child['slots'])->map(fn ($s) => "{$s['start']} ({$s['minutes']} phút)")->implode(', ');

            return "{$child['name']} học {$slots}";
        })->implode(' · ').'. Phụ huynh nhắc con vào học đúng giờ nhé.';
    }
}
