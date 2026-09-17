<?php

namespace App\Jobs;

use App\Mail\WeeklyParentReport;
use App\Models\ParentProfile;
use App\Models\User;
use App\Services\Learning\StudentReportService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;

/**
 * Gửi báo cáo tuần cho MỘT phụ huynh. Mỗi phụ huynh một job để một địa chỉ mail lỗi
 * không chặn cả lượt gửi.
 */
class SendWeeklyParentReport implements ShouldQueue
{
    use Queueable;

    /** Chạy lại lệnh trong vòng chừng này ngày thì bỏ qua — chống gửi trùng. */
    public const MIN_DAYS_BETWEEN = 6;

    public int $tries = 3;

    public function __construct(public readonly int $parentId) {}

    public function handle(StudentReportService $reports): void
    {
        $parent = User::with('parentProfile')->find($this->parentId);
        $profile = $parent?->parentProfile;

        if (! $parent || ! $profile?->weekly_report_enabled) {
            return;
        }

        if ($profile->last_weekly_report_at?->greaterThan(now()->subDays(self::MIN_DAYS_BETWEEN))) {
            return;
        }

        $to = now();
        $from = $to->copy()->subDays(7);

        $children = $parent->linkedChildren()->orderBy('name')->get()
            ->map(fn (User $child) => $reports->weekly($child, $from, $to))
            // Tuần con không học gì và không có gì cần biết → không gửi thư rỗng.
            ->filter(fn (array $week) => $reports->hasWeeklyActivity($week))
            ->values()
            ->all();

        if ($children === []) {
            return;
        }

        Mail::to($parent)->send(new WeeklyParentReport($parent, $children, $from, $to));

        ParentProfile::whereKey($profile->id)->update(['last_weekly_report_at' => $to]);
    }
}
