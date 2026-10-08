<?php

namespace App\Console\Commands;

use App\Models\LearningPath;
use App\Models\Role;
use App\Models\StudentDailyActivity;
use App\Models\StudentTopicMastery;
use App\Models\StudySession;
use App\Models\User;
use App\Notifications\StudyReminder;
use App\Services\Learning\LearningPathService;
use Illuminate\Console\Command;

/**
 * Nhắc học hằng ngày + cảnh báo sắp quên bài (đặc tả module 9).
 *
 * Chạy 19:00. Chỉ nhắc học sinh ĐANG học (có lộ trình hoặc có học trong 30 ngày qua) mà hôm nay chưa học đủ
 * MIN_ACTIVE_MINUTES — nhắc tài khoản bỏ từ lâu chỉ khiến họ tắt thông báo. Mỗi ngày tối đa một lần.
 */
class RemindStudy extends Command
{
    public const MIN_ACTIVE_MINUTES = 5;

    public const ACTIVE_WITHIN_DAYS = 30;

    protected $signature = 'students:remind-study';

    protected $description = 'Nhắc học sinh chưa học hôm nay; ưu tiên nhắc chủ đề sắp quên';

    public function handle(): int
    {
        $sent = 0;

        $studiedToday = StudentDailyActivity::whereDate('activity_date', today())
            ->where('active_seconds', '>=', self::MIN_ACTIVE_MINUTES * 60)
            ->pluck('user_id');

        $remindedToday = User::query()
            ->whereHas('notifications', fn ($q) => $q->where('type', StudyReminder::class)->whereDate('created_at', today()))
            ->pluck('id');

        User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::STUDENT))
            ->whereNotIn('id', $studiedToday->merge($remindedToday))
            ->where(fn ($q) => $q
                ->whereExists(fn ($s) => $s->from('learning_paths')->whereColumn('learning_paths.user_id', 'users.id')
                    ->where('learning_paths.status', LearningPath::STATUS_ACTIVE))
                ->orWhereExists(fn ($s) => $s->from('student_daily_activity')->whereColumn('student_daily_activity.user_id', 'users.id')
                    ->whereDate('activity_date', '>=', today()->subDays(self::ACTIVE_WITHIN_DAYS))))
            ->chunkById(200, function ($students) use (&$sent) {
                foreach ($students as $student) {
                    $student->notify($this->reminderFor($student));
                    $sent++;
                }
            });

        $this->info("Đã nhắc {$sent} học sinh.");

        return self::SUCCESS;
    }

    private function reminderFor(User $student): StudyReminder
    {
        // Chủ đề đã vững nhưng lâu không luyện — nhắc cái này trước vì quên rồi học lại tốn hơn nhiều.
        $forgetting = StudentTopicMastery::query()
            ->where('user_id', $student->id)
            ->where('mastery_score', '>=', StudentTopicMastery::WEAK_THRESHOLD)
            ->where('last_practiced_at', '<', now()->subDays(LearningPathService::FORGET_AFTER_DAYS))
            ->orderBy('last_practiced_at')
            ->with('topic')
            ->first();

        $session = StudySession::query()
            ->where('user_id', $student->id)
            ->where('status', '!=', StudySession::STATUS_DONE)
            ->whereHas('path', fn ($q) => $q->where('status', LearningPath::STATUS_ACTIVE))
            ->orderBy('session_no')
            ->first();

        return new StudyReminder(
            forgettingTopic: $forgetting?->topic?->name,
            sessionNo: $session?->session_no,
            url: $session ? route('student.path.show') : route('student.dashboard'),
        );
    }
}
