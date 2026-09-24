<?php

namespace App\Notifications;

use App\Models\QaQuestion;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

/** Câu trả lời của bạn được chọn là lời giải. */
class QaAnswerAccepted extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly QaQuestion $question) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? [] : ['database', WebPushChannel::class];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Câu trả lời của bạn được chọn',
            'message' => $this->line(),
            'url' => route('student.qa.show', $this->question),
            'icon' => 'bi-award',
        ];
    }

    /** @return array<string, string> */
    public function toPush(object $notifiable): array
    {
        return [
            'title' => 'Câu trả lời của bạn được chọn',
            'body' => $this->line(),
            'url' => route('student.qa.show', $this->question),
        ];
    }

    private function line(): string
    {
        return 'Ở câu hỏi "'.Str::limit($this->question->title, 60).'".';
    }
}
