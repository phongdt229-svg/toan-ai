<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/**
 * Nhắc học hằng ngày + cảnh báo sắp quên bài (đặc tả module 9).
 *
 * Không có kênh mail — cùng lý do với AssignmentDueSoon: ngày nào cũng gửi thì email là rác.
 * Có chủ đề sắp quên thì nói thẳng chủ đề đó: "ôn «Phân số» 10 phút" dễ bấm hơn "vào học đi".
 */
class StudyReminder extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly ?string $forgettingTopic,
        public readonly ?int $sessionNo,
        public readonly string $url,
        public readonly int $streak = 0,
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
            'title' => $this->title(),
            'message' => $this->line(),
            'url' => $this->url,
            'icon' => $this->forgettingTopic ? 'bi-arrow-repeat' : 'bi-book',
        ];
    }

    /** @return array<string, string> */
    public function toPush(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->line(), 'url' => $this->url];
    }

    private function title(): string
    {
        return match (true) {
            $this->forgettingTopic !== null => 'Sắp quên bài rồi',
            $this->streak >= 2 => "Giữ chuỗi {$this->streak} ngày nhé 🔥",
            default => 'Hôm nay em chưa học',
        };
    }

    private function line(): string
    {
        // Chuỗi đang dài là lý do mạnh nhất để mở app tối nay — nói nó trước.
        $keep = $this->streak >= 2 ? "Em đang có chuỗi {$this->streak} ngày học liên tiếp — học thêm vài phút để giữ chuỗi. " : '';

        return $keep.$this->body();
    }

    private function body(): string
    {
        if ($this->forgettingTopic) {
            return "Lâu rồi em chưa ôn «{$this->forgettingTopic}» — dành 10 phút luyện lại kẻo quên nhé.";
        }

        return $this->sessionNo
            ? "Buổi {$this->sessionNo} trong lộ trình của em đang chờ. Học một chút trước khi ngủ nhé!"
            : 'Dành 15 phút luyện Toán hôm nay để giữ nhịp học nhé!';
    }
}
