<?php

namespace App\Services\Rewards;

use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\RewardPoint;
use App\Models\RewardRule;
use App\Models\SchoolClass;
use App\Models\StudentDailyActivity;
use App\Models\StudySession;
use App\Models\User;
use App\Services\Admin\ImpersonationService;
use App\Services\Learning\StreakService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Điểm thưởng — chỉ để mở huy hiệu và xếp hạng tuần, không đổi ra tiền.
 *
 * Mọi lần cộng đi qua `award()`: ghi một dòng sổ cái, unique theo (học sinh, lý do, nguồn) nên gọi lại
 * bao nhiêu lần cũng chỉ cộng một lần. Người hỗ trợ đang mượn tài khoản thì không cộng.
 */
class RewardService
{
    public const LESSON_COMPLETED = 'lesson_completed';

    public const EXAM_FINISHED = 'exam_finished';

    public const EXAM_HIGH_SCORE = 'exam_high_score';

    public const PATH_SESSION_DONE = 'path_session_done';

    public const STUDY_DAY = 'streak_day';

    /** Ngưỡng "điểm giỏi" của một đề — cùng mốc 80% với luật học bài mới của lộ trình. */
    public const HIGH_SCORE_PERCENT = 80;

    public function __construct(private readonly BadgeService $badges) {}

    public function forLesson(User $student, Lesson $lesson): void
    {
        $this->award($student, self::LESSON_COMPLETED, 'lesson', $lesson->id);
    }

    /**
     * Nguồn là ĐỀ, không phải lượt làm: làm lại một đề 10 lần không được 10 lần điểm.
     * Lượt chấm tay tự luận làm điểm vượt 80% sau này vẫn được cộng (sự kiện bắn lại).
     */
    public function forExamAttempt(ExamAttempt $attempt): void
    {
        $student = $attempt->loadMissing('user')->user;

        $this->award($student, self::EXAM_FINISHED, 'exam', $attempt->exam_id);

        $total = (float) $attempt->total_points;
        if ($total > 0 && (float) $attempt->score / $total * 100 >= self::HIGH_SCORE_PERCENT) {
            $this->award($student, self::EXAM_HIGH_SCORE, 'exam', $attempt->exam_id);
        }
    }

    public function forStudySession(User $student, StudySession $session): void
    {
        $this->award($student, self::PATH_SESSION_DONE, 'study_session', $session->id);
    }

    /** Gọi sau mỗi heartbeat: hôm nay vừa học đủ ngưỡng streak thì cộng (một lần mỗi ngày). */
    public function forStudyDay(User $student, StudentDailyActivity $day): void
    {
        if ($day->active_seconds < StreakService::minActiveMinutes() * 60) {
            return;
        }

        $this->award($student, self::STUDY_DAY, 'day', (int) $day->activity_date->format('Ymd'));
    }

    /** @return bool true nếu lần này thật sự được cộng */
    public function award(User $user, string $reason, string $sourceType, int $sourceId): bool
    {
        if (! $user->isStudent() || $this->impersonating()) {
            return false;
        }

        $rule = $this->rules()->get($reason);
        if (! $rule || ! $rule->is_active || $rule->points <= 0) {
            return false;
        }

        $inserted = RewardPoint::insertOrIgnore([
            'user_id' => $user->id,
            'points' => $rule->points,
            'reason' => $reason,
            'source_type' => $sourceType,
            'source_id' => $sourceId,
            'created_at' => now(),
        ]);

        if ($inserted === 0) {
            return false;
        }

        $this->badges->evaluate($user);

        return true;
    }

    public function total(User $user): int
    {
        return (int) RewardPoint::where('user_id', $user->id)->sum('points');
    }

    public function sinceWeekStart(User $user): int
    {
        return (int) RewardPoint::where('user_id', $user->id)->where('created_at', '>=', now()->startOfWeek())->sum('points');
    }

    /** @return Collection<int, RewardPoint> */
    public function history(User $user, int $limit = 20): Collection
    {
        return RewardPoint::where('user_id', $user->id)->latest('id')->limit($limit)->get();
    }

    /** @return Collection<string, RewardRule> */
    public function rules(): Collection
    {
        return once(fn () => RewardRule::all()->keyBy('reason'));
    }

    /**
     * Bảng xếp hạng điểm tuần này của một lớp. Học sinh bật "ẩn tên" thì hiện "Bạn học ẩn danh",
     * trừ với chính em đó. Không hiện email hay thông tin nào khác ngoài tên.
     *
     * @return Collection<int, array{id: int, name: string, points: int, rank: int, me: bool}>
     */
    public function leaderboard(SchoolClass $class, User $viewer, ?Carbon $since = null, int $limit = 10): Collection
    {
        $since ??= now()->startOfWeek();

        $points = DB::table('reward_points')
            ->where('created_at', '>=', $since)
            ->groupBy('user_id')
            ->selectRaw('user_id, SUM(points) as total');

        $rows = DB::table('class_students')
            ->where('class_students.class_id', $class->id)
            ->where('class_students.status', 'active')
            ->join('users', 'users.id', '=', 'class_students.student_id')
            ->whereNull('users.deleted_at')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->leftJoinSub($points, 'p', 'p.user_id', '=', 'users.id')
            ->select('users.id', 'users.name', 'student_profiles.hide_from_leaderboard')
            ->selectRaw('COALESCE(p.total, 0) as points')
            ->orderByDesc('points')
            ->orderBy('users.id')
            ->get();

        $rank = 0;
        $last = null;

        return $rows->values()->map(function ($row, $i) use ($viewer, &$rank, &$last) {
            // Bằng điểm thì cùng hạng.
            if ($last === null || (int) $row->points !== $last) {
                $rank = $i + 1;
                $last = (int) $row->points;
            }
            $me = (int) $row->id === $viewer->id;

            return [
                'id' => (int) $row->id,
                'name' => $row->hide_from_leaderboard && ! $me ? 'Bạn học ẩn danh' : $row->name,
                'points' => (int) $row->points,
                'rank' => $rank,
                'me' => $me,
            ];
        })->filter(fn ($r, $i) => $i < $limit || $r['me'])->values();
    }

    private function impersonating(): bool
    {
        if (! app()->bound('request')) {
            return false;
        }
        $request = request();

        return $request->hasSession() && $request->session()->has(ImpersonationService::SESSION_KEY);
    }
}
