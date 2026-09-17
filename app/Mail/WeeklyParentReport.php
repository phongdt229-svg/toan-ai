<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;

class WeeklyParentReport extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Không đặt tên $from / $to: Mailable đã dùng hai tên đó cho người gửi / người nhận.
     *
     * @param  array<int, array<string, mixed>>  $children  kết quả StudentReportService::weekly() cho từng con
     */
    public function __construct(
        public readonly User $parent,
        public readonly array $children,
        public readonly Carbon $periodStart,
        public readonly Carbon $periodEnd,
    ) {}

    public function envelope(): Envelope
    {
        $names = collect($this->children)->pluck('student.name')->implode(', ');

        return new Envelope(
            subject: "Báo cáo học tập tuần {$this->periodStart->format('d/m')}–{$this->periodEnd->format('d/m')}: {$names}",
        );
    }

    public function content(): Content
    {
        return new Content(markdown: 'emails.parents.weekly-report');
    }
}
