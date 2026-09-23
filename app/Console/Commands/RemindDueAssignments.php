<?php

namespace App\Console\Commands;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Notifications\AssignmentDueSoon;
use Illuminate\Console\Command;

/**
 * Nhắc học sinh bài giao sắp tới hạn mà chưa nộp.
 *
 * Chạy 18:00 — sau giờ học, còn kịp làm buổi tối. Chỉ nhắc một lần cho mỗi bài
 * (`due_reminded_at`), vì nhắc nhiều lần cùng một việc là cách nhanh nhất để người ta tắt thông báo.
 */
class RemindDueAssignments extends Command
{
    /** Chỉ nhắc khi hạn nằm trong khoảng này. */
    public const HOURS_AHEAD = 24;

    protected $signature = 'assignments:remind-due';

    protected $description = 'Nhắc học sinh các bài giao sắp hết hạn mà chưa nộp';

    public function handle(): int
    {
        $sent = 0;

        AssignmentStudent::query()
            ->where('status', AssignmentStudent::STATUS_ASSIGNED)
            ->whereNull('due_reminded_at')
            ->whereHas('assignment', fn ($q) => $q
                // Bài nháp chưa công bố thì học sinh còn chưa thấy, nhắc là khó hiểu.
                ->where('status', Assignment::STATUS_PUBLISHED)
                ->whereNotNull('due_at')
                ->where('due_at', '>', now())
                ->where('due_at', '<=', now()->addHours(self::HOURS_AHEAD))
                // Quan hệ assignment có withTrashed — bài đã xoá thì thôi (CLAUDE.md).
                ->whereNull('deleted_at'))
            ->with('assignment', 'student')
            ->each(function (AssignmentStudent $row) use (&$sent) {
                if (! $row->student) {
                    return;
                }

                $hoursLeft = max(1, (int) ceil(now()->diffInMinutes($row->assignment->due_at, false) / 60));

                $row->student->notify(new AssignmentDueSoon($row->assignment, $hoursLeft));
                $row->forceFill(['due_reminded_at' => now()])->saveQuietly();
                $sent++;
            });

        $this->info("Đã nhắc {$sent} lượt bài giao sắp hết hạn.");

        return self::SUCCESS;
    }
}
