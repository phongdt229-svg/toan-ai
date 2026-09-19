<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

/** Phụ huynh: con vừa làm một đề kiểm tra với điểm thấp (§14) — cần hỗ trợ thêm. */
class ChildScoreLow extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly User $child,
        public readonly string $examTitle,
        public readonly float $percent,
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
            'title' => 'Con cần hỗ trợ thêm',
            'message' => "{$this->child->name} chỉ đạt ".round($this->percent)."% ở đề \"{$this->examTitle}\" — nên xem lại cùng con.",
            'url' => route('parent.children.show', $this->child),
            'icon' => 'bi-exclamation-triangle',
        ];
    }
}
