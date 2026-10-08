<?php

namespace App\Notifications;

use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/** Báo cáo cuối ngày (TA-13) — phụ huynh tự bật trong Cài đặt nên có cả email. */
class DailyChildReport extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  array<int, array{name: string, active: int, online: int, attendance: ?string, quizzes: array<int, int>, risk: ?string}>  $lines */
    public function __construct(
        public readonly Carbon $day,
        public readonly array $lines,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable->hasMutedNotification(self::class) ? [] : ['database', WebPushChannel::class, 'mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Báo cáo học tập ngày '.$this->day->format('d/m'),
            'message' => collect($this->lines)->map(fn ($l) => $this->summary($l))->implode(' · '),
            'url' => route('parent.dashboard'),
            'icon' => 'bi-journal-check',
        ];
    }

    /** @return array<string, string> */
    public function toPush(object $notifiable): array
    {
        return ['title' => 'Báo cáo học tập hôm nay', 'body' => $this->toArray($notifiable)['message'], 'url' => route('parent.dashboard')];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject('[TOÁN AI] Báo cáo học tập ngày '.$this->day->format('d/m/Y'))
            ->greeting("Chào {$notifiable->name},");

        foreach ($this->lines as $line) {
            $mail->line('**'.$line['name'].'** — '.$this->summary($line, withName: false));
        }

        return $mail
            ->line('"Học thực" chỉ tính lúc con có thao tác trên trang học; để ứng dụng mở mà không học thì không được tính.')
            ->action('Xem chi tiết', route('parent.dashboard'))
            ->line('Tắt báo cáo cuối ngày trong Cài đặt nếu phụ huynh không cần.');
    }

    /** @param  array{name: string, active: int, online: int, attendance: ?string, quizzes: array<int, int>, risk: ?string}  $l */
    private function summary(array $l, bool $withName = true): string
    {
        $parts = ["học thực {$l['active']}/{$l['online']} phút online"];

        if ($l['attendance']) {
            $parts[] = 'điểm danh: '.mb_strtolower($l['attendance']);
        }
        if ($l['quizzes']) {
            $parts[] = 'kiểm tra cuối buổi '.implode(', ', array_map(fn ($p) => "{$p}%", $l['quizzes']));
        }
        if ($l['risk']) {
            $parts[] = 'tình hình: '.mb_strtolower($l['risk']);
        }

        return ($withName ? $l['name'].': ' : '').implode(', ', $parts);
    }
}
