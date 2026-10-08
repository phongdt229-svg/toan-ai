<?php

namespace App\Services\Learning;

use App\Models\StudentAttendance;
use App\Models\StudentDailyActivity;
use App\Models\StudySession;
use App\Models\User;
use App\Notifications\StudyReminder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Learning Risk Score (TA-11, đặc tả "Logic"): nguy cơ bỏ học / lệch tiến độ trong 7 ngày gần nhất.
 *
 *   risk = 0.30·tỉ lệ vắng + 0.20·tỉ lệ buổi dở dang + 0.20·tỉ lệ ngày tập trung thấp
 *        + 0.15·mức giảm điểm kiểm tra + 0.15·tỉ lệ được nhắc mà không học        (× 100)
 *
 * 0–30 ổn định (xanh) · 31–60 cần theo dõi (vàng) · 61–100 nguy cơ cao (đỏ).
 * Tính theo lô cho cả lớp: mỗi thành phần một truy vấn, không truy vấn theo từng học sinh.
 */
class RiskScoreService
{
    public const WINDOW_DAYS = 7;

    public const WEIGHTS = [
        'absenteeism' => 0.30,
        'incomplete' => 0.20,
        'low_engagement' => 0.20,
        'quiz_decline' => 0.15,
        'ignored_reminders' => 0.15,
    ];

    public const COMPONENT_LABELS = [
        'absenteeism' => 'Vắng buổi theo lịch',
        'incomplete' => 'Buổi học dở dang',
        'low_engagement' => 'Ngày học kém tập trung',
        'quiz_decline' => 'Điểm kiểm tra đi xuống',
        'ignored_reminders' => 'Được nhắc mà không học',
    ];

    public const LEVELS = [
        'green' => ['label' => 'Ổn định', 'color' => 'success'],
        'yellow' => ['label' => 'Cần theo dõi', 'color' => 'warning'],
        'red' => ['label' => 'Nguy cơ cao', 'color' => 'danger'],
    ];

    /** Ngày có học dưới mức này không tính vào "tập trung" (mở app vài phút thì chưa nói lên gì). */
    private const MIN_ONLINE_SECONDS = 300;

    /**
     * @return array{score: int, level: string, label: string, color: string, components: array<string, float>}|null
     *                                                                                                               null = 7 ngày qua chưa có dữ liệu nào để chấm
     */
    public function forStudent(User $student): ?array
    {
        return $this->forStudents(collect([$student->id]))->get($student->id);
    }

    /**
     * @param  Collection<int, int>  $studentIds
     * @return Collection<int, array|null> keyBy student id
     */
    public function forStudents(Collection $studentIds): Collection
    {
        $ids = $studentIds->unique()->values();
        $since = today()->subDays(self::WINDOW_DAYS - 1);

        if ($ids->isEmpty()) {
            return collect();
        }

        $attendance = StudentAttendance::whereIn('student_id', $ids)
            ->whereIn('status', StudentAttendance::FINAL_STATUSES)
            ->whereDate('attendance_date', '>=', $since)
            ->get(['student_id', 'status'])
            ->groupBy('student_id');

        $activity = StudentDailyActivity::whereIn('user_id', $ids)
            ->whereDate('activity_date', '>=', $since)
            ->get(['user_id', 'activity_date', 'online_seconds', 'active_seconds'])
            ->groupBy('user_id');

        $quizzes = StudySession::whereIn('user_id', $ids)
            ->whereNotNull('quiz_percent')
            ->where('quiz_submitted_at', '>=', now()->subDays(30))
            ->orderByDesc('quiz_submitted_at')
            ->get(['user_id', 'quiz_percent'])
            ->groupBy('user_id');

        $reminders = DB::table('notifications')
            ->where('notifiable_type', User::class)
            ->whereIn('notifiable_id', $ids)
            ->where('type', StudyReminder::class)
            ->where('created_at', '>=', $since)
            ->get(['notifiable_id', 'created_at'])
            ->groupBy('notifiable_id');

        return $ids->mapWithKeys(fn (int $id) => [$id => $this->score(
            $attendance->get($id, collect()),
            $activity->get($id, collect()),
            $quizzes->get($id, collect())->take(AttendanceService::DECLINE_SESSIONS)->pluck('quiz_percent')->all(),
            $reminders->get($id, collect()),
        )]);
    }

    /**
     * Gợi ý can thiệp cho phụ huynh (TA-16, mục 6 của đặc tả "giám sát phụ huynh"), suy ra từ chính
     * các thành phần làm điểm rủi ro tăng — để phụ huynh biết NÊN LÀM GÌ, không chỉ thấy màu đỏ.
     *
     * @param  array{components: array<string, float>}|null  $risk
     * @return array<int, string>
     */
    public function interventions(?array $risk): array
    {
        if (! $risk) {
            return [];
        }

        $c = $risk['components'];
        $tips = [];

        if ($c['absenteeism'] >= 0.3) {
            $tips[] = 'Con hay vắng buổi theo lịch — thử hỏi con khung giờ nào hợp hơn rồi đổi lịch cùng con.';
        }
        if ($c['ignored_reminders'] >= 0.5) {
            $tips[] = 'Con thường bỏ qua lời nhắc học — phụ huynh nhắc trực tiếp vào giờ học sẽ hiệu quả hơn.';
        }
        if ($c['low_engagement'] >= 0.5) {
            $tips[] = 'Con mở ứng dụng nhưng ít thao tác — ngồi cùng con 10 phút đầu buổi giúp con vào nhịp.';
        }
        if ($c['incomplete'] >= 0.3) {
            $tips[] = 'Nhiều buổi con học chưa đủ thời lượng hoặc bỏ kiểm tra cuối buổi — nên giữ trọn buổi, kể cả khi học ngắn hơn.';
        }
        if ($c['quiz_decline'] >= 0.5) {
            $tips[] = 'Điểm kiểm tra đang giảm — lộ trình đã tự thêm phần ôn; phụ huynh động viên con làm hết phần ôn trước khi học bài mới.';
        }

        return $tips;
    }

    public static function levelFor(int $score): string
    {
        return match (true) {
            $score <= 30 => 'green',
            $score <= 60 => 'yellow',
            default => 'red',
        };
    }

    /**
     * @param  array<int, int>  $lastQuizzes  điểm %, mới nhất trước
     */
    private function score(Collection $attendance, Collection $activity, array $lastQuizzes, Collection $reminders): ?array
    {
        if ($attendance->isEmpty() && $activity->isEmpty() && $reminders->isEmpty()) {
            return null;
        }

        $sessions = $attendance->count();
        $engagedDays = $activity->filter(fn ($d) => $d->online_seconds >= self::MIN_ONLINE_SECONDS);

        // Ngày được nhắc mà hôm đó học thực dưới 5 phút = nhắc mà không học.
        $activeByDate = $activity->mapWithKeys(fn ($d) => [$d->activity_date->toDateString() => $d->active_seconds]);
        $ignored = $reminders->filter(fn ($r) => ($activeByDate[substr((string) $r->created_at, 0, 10)] ?? 0) < 300)->count();

        $components = [
            'absenteeism' => $sessions ? $attendance->where('status', StudentAttendance::STATUS_ABSENT)->count() / $sessions : 0.0,
            'incomplete' => $sessions ? $attendance->where('status', StudentAttendance::STATUS_PARTIAL)->count() / $sessions : 0.0,
            'low_engagement' => $engagedDays->isNotEmpty()
                ? $engagedDays->filter(fn ($d) => $d->active_seconds / $d->online_seconds * 100 < ActivityService::FOCUS_LOW)->count() / $engagedDays->count()
                : 0.0,
            'quiz_decline' => $this->quizDecline($lastQuizzes),
            'ignored_reminders' => $reminders->isNotEmpty() ? $ignored / $reminders->count() : 0.0,
        ];

        $score = (int) round(100 * collect(self::WEIGHTS)->map(fn ($w, $k) => $w * $components[$k])->sum());
        $level = self::levelFor($score);

        return [
            'score' => $score,
            'level' => $level,
            'label' => self::LEVELS[$level]['label'],
            'color' => self::LEVELS[$level]['color'],
            'components' => array_map(fn ($v) => round($v, 2), $components),
        ];
    }

    /** 1 = giảm 3 bài liền · 0.5 = bài mới nhất tụt ≥ 20 điểm so với bài trước · 0 = còn lại. */
    private function quizDecline(array $last): float
    {
        if (count($last) >= AttendanceService::DECLINE_SESSIONS && $last[0] < $last[1] && $last[1] < $last[2]) {
            return 1.0;
        }

        return count($last) >= 2 && $last[1] - $last[0] >= 20 ? 0.5 : 0.0;
    }
}
