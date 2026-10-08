<?php

namespace App\Notifications;

use App\Models\StudentAttendance;
use App\Models\User;
use App\Notifications\Channels\WebPushChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Carbon;

/**
 * Báo phụ huynh khi con chưa vào học / vắng buổi theo lịch (TA-10, đặc tả "Logic" — cảnh báo phụ huynh).
 *
 * Chưa vào học: chuông + đẩy (cần biết ngay, còn kịp nhắc con). Vắng: thêm email khi vắng từ 2 buổi liên tiếp —
 * đó là mức "cảnh báo cao" của đặc tả, đáng để vào hộp thư; vắng lẻ một buổi thì không làm phiền bằng email.
 */
class ChildAttendanceAlert extends Notification implements ShouldQueue
{
    use Queueable;

    public const NOT_STARTED = 'not_started';

    public const ABSENT = 'absent';

    public const QUIZ_MISSED = 'quiz_missed';

    public const QUIZ_DECLINE = 'quiz_decline';

    public const STARTED = 'started';

    public const FINISHED = 'finished';

    public const HIGH_ALERT_STREAK = 2;

    public function __construct(
        public readonly User $student,
        public readonly StudentAttendance $attendance,
        public readonly string $kind,
        public readonly int $absentStreak = 0,
        public readonly ?Carbon $nextSlot = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        if ($notifiable->hasMutedNotification(self::class)) {
            return [];
        }

        return $this->isHighAlert()
            ? ['database', WebPushChannel::class, 'mail']
            : ['database', WebPushChannel::class];
    }

    public function isHighAlert(): bool
    {
        return $this->kind === self::ABSENT && $this->absentStreak >= self::HIGH_ALERT_STREAK;
    }

    /** @return array<string, mixed> */
    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->line(),
            'url' => route('parent.children.show', $this->student),
            'icon' => match ($this->kind) {
                self::ABSENT => 'bi-person-x',
                self::QUIZ_DECLINE => 'bi-graph-down-arrow',
                self::QUIZ_MISSED => 'bi-clipboard-x',
                self::STARTED => 'bi-play-circle',
                self::FINISHED => 'bi-check2-circle',
                default => 'bi-alarm',
            },
            'level' => $this->isHighAlert() ? 'high' : 'normal',
        ];
    }

    /** @return array<string, string> */
    public function toPush(object $notifiable): array
    {
        return ['title' => $this->title(), 'body' => $this->line(), 'url' => route('parent.children.show', $this->student)];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("[TOÁN AI] {$this->student->name} vắng {$this->absentStreak} buổi học liên tiếp")
            ->greeting("Chào {$notifiable->name},")
            ->line($this->line())
            ->line('Phụ huynh có thể trò chuyện với con về giờ học, hoặc đổi khung giờ cho phù hợp hơn trong trang Lịch học.')
            ->action('Xem tình hình học của con', route('parent.children.show', $this->student));
    }

    private function title(): string
    {
        return match (true) {
            $this->isHighAlert() => "{$this->student->name} vắng {$this->absentStreak} buổi liên tiếp",
            $this->kind === self::ABSENT => "{$this->student->name} vắng buổi học",
            $this->kind === self::QUIZ_MISSED => "{$this->student->name} bỏ kiểm tra cuối buổi",
            $this->kind === self::QUIZ_DECLINE => "Điểm của {$this->student->name} đang giảm",
            $this->kind === self::STARTED => "{$this->student->name} đã vào học",
            $this->kind === self::FINISHED => "{$this->student->name} học xong buổi",
            default => "{$this->student->name} chưa vào học",
        };
    }

    private function line(): string
    {
        $slot = $this->attendance->scheduled_start->format('H:i d/m');

        if ($this->kind === self::STARTED) {
            $late = $this->attendance->late_minutes ? " (trễ {$this->attendance->late_minutes} phút)" : ' đúng giờ';

            return "Con đã bắt đầu buổi {$slot}{$late}.";
        }

        if ($this->kind === self::FINISHED) {
            return "Buổi {$slot}: {$this->attendance->label()} — học thực {$this->attendance->activeMinutes()}/{$this->attendance->scheduled_minutes} phút"
                .($this->attendance->quiz_submitted ? ', đã làm kiểm tra cuối buổi.' : '.');
        }

        if ($this->kind === self::QUIZ_MISSED) {
            return "Buổi {$slot} con học đủ giờ nhưng chưa làm bài kiểm tra cuối buổi, nên buổi học chưa tính là hoàn thành.";
        }

        if ($this->kind === self::QUIZ_DECLINE) {
            return 'Điểm kiểm tra cuối buổi của con giảm 3 buổi liên tiếp. Phụ huynh nên theo dõi thêm — có thể con đang gặp phần kiến thức khó, buổi tới hệ thống sẽ xếp thêm phần ôn.';
        }

        if ($this->kind === self::NOT_STARTED) {
            return "Đã quá 15 phút từ giờ học ({$slot}) mà con chưa vào học. Phụ huynh nhắc con giúp nhé.";
        }

        $next = $this->nextSlot ? " Buổi kế tiếp theo lịch: {$this->nextSlot->format('H:i')} ngày {$this->nextSlot->format('d/m')} — con có thể học bù vào buổi đó." : '';

        return "Con không học buổi {$slot} (học thực {$this->attendance->activeMinutes()}/{$this->attendance->scheduled_minutes} phút).{$next}";
    }
}
