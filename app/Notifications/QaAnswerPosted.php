<?php

namespace App\Notifications;

use App\Models\QaQuestion;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/**
 * Câu hỏi của bạn vừa có người trả lời.
 *
 * CỐ Ý không có kênh mail — cùng lý do với nhắc bài giao: hỏi đáp phát sinh liên tục,
 * gửi email là biến hộp thư thành rác và học sinh cũng không mở email.
 */
class QaAnswerPosted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly QaQuestion $question,
        public readonly User $answerer,
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
            'title' => 'Câu hỏi của bạn có trả lời mới',
            'message' => $this->line(),
            'url' => route('student.qa.show', $this->question),
            'icon' => 'bi-chat-dots',
        ];
    }

    /** @return array<string, string> */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'Câu hỏi của bạn có trả lời mới',
            'body' => $this->line(),
            'url' => route('student.qa.show', $this->question),
        ];
    }

    private function line(): string
    {
        return "{$this->answerer->name} vừa trả lời: \"".Str::limit($this->question->title, 60).'"';
    }
}
