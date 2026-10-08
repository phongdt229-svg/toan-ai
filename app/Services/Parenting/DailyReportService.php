<?php

namespace App\Services\Parenting;

use App\Models\ParentProfile;
use App\Models\StudentAttendance;
use App\Models\StudentDailyActivity;
use App\Models\StudySession;
use App\Models\User;
use App\Notifications\DailyChildReport;
use App\Services\Learning\RiskScoreService;
use Illuminate\Support\Carbon;

/**
 * Báo cáo cuối ngày cho phụ huynh (TA-13). Một thông báo gom mọi con — không phải một thư cho mỗi con.
 * Con hôm nay không có lịch và không học thì bỏ qua; mọi con đều trống thì không gửi gì.
 */
class DailyReportService
{
    public function __construct(private readonly RiskScoreService $risk) {}

    /** @return int số phụ huynh đã gửi */
    public function sendAll(?Carbon $day = null): int
    {
        $day ??= today();
        $sent = 0;

        ParentProfile::query()
            ->where('daily_report_enabled', true)
            ->where(fn ($q) => $q->whereNull('last_daily_report_at')->orWhereDate('last_daily_report_at', '<', $day))
            ->with('user')
            ->chunkById(100, function ($profiles) use ($day, &$sent) {
                foreach ($profiles as $profile) {
                    if (! $profile->user || $profile->user->status !== User::STATUS_ACTIVE) {
                        continue;
                    }

                    $lines = $this->linesFor($profile->user, $day);

                    if ($lines !== []) {
                        $profile->user->notify(new DailyChildReport($day, $lines));
                        $sent++;
                    }

                    // Đánh dấu cả khi không gửi: chạy lại trong ngày không phải tính lại.
                    $profile->forceFill(['last_daily_report_at' => now()])->save();
                }
            });

        return $sent;
    }

    /**
     * @return array<int, array{name: string, active: int, online: int, attendance: ?string, quizzes: array<int, int>, risk: ?string}>
     */
    public function linesFor(User $parent, Carbon $day): array
    {
        $children = $parent->linkedChildren()->get();
        $risks = $this->risk->forStudents($children->pluck('id'));
        $lines = [];

        foreach ($children as $child) {
            $activity = StudentDailyActivity::where('user_id', $child->id)->whereDate('activity_date', $day)->first();
            $attendance = StudentAttendance::where('student_id', $child->id)->whereDate('attendance_date', $day)->first();

            if (! $attendance && ($activity?->online_seconds ?? 0) < 60) {
                continue;
            }

            $lines[] = [
                'name' => $child->name,
                'active' => $activity?->activeMinutes() ?? 0,
                'online' => $activity?->onlineMinutes() ?? 0,
                'attendance' => $attendance?->label(),
                'quizzes' => StudySession::where('user_id', $child->id)->whereDate('quiz_submitted_at', $day)
                    ->orderBy('quiz_submitted_at')->pluck('quiz_percent')->map(fn ($p) => (int) $p)->all(),
                'risk' => $risks->get($child->id)['label'] ?? null,
            ];
        }

        return $lines;
    }
}
