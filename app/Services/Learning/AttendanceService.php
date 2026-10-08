<?php

namespace App\Services\Learning;

use App\Models\LearningPath;
use App\Models\StudentAttendance;
use App\Models\StudentDailyActivity;
use App\Models\StudySchedule;
use App\Models\StudySession;
use App\Models\User;
use App\Notifications\ChildAttendanceAlert;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Điểm danh theo lịch học (TA-09) + xử lý vắng (TA-10). Chạy mỗi phút qua `attendance:tick`.
 *
 * Một buổi đi qua các trạng thái:
 *   pending (tới giờ) → in_progress (có thao tác học)
 *                     → late (quá LATE_AFTER phút chưa vào) → absent_pending (quá NOT_STARTED_AFTER phút, báo phụ huynh)
 *   hết khung giờ → chốt present / partial / absent.
 *
 * Luật chốt (đặc tả "Logic"):
 *   Present = học thực ≥ PRESENT_ACTIVE_PERCENT% thời lượng VÀ (đã nộp kiểm tra cuối buổi trong khung, hoặc chưa có lộ trình)
 *   Absent  = học thực dưới ABSENT_BELOW_MINUTES phút ("không vào hoặc gần như không tương tác")
 *   Partial = còn lại (có vào nhưng chưa đủ thời lượng / bỏ kiểm tra).
 */
class AttendanceService
{
    public const LATE_AFTER_MINUTES = 10;

    public const NOT_STARTED_AFTER_MINUTES = 15;

    public const PRESENT_ACTIVE_PERCENT = 70;

    public const ABSENT_BELOW_MINUTES = 2;

    public const IDLE_FLAG_MINUTES = 10;

    public const DECLINE_SESSIONS = 3;

    /** @return array{created: int, finalized: int} */
    public function tick(?Carbon $now = null): array
    {
        $now ??= now();

        $created = $this->openDueSessions($now);
        $finalized = 0;

        StudentAttendance::query()
            ->whereNotIn('status', StudentAttendance::FINAL_STATUSES)
            ->with('student')
            ->chunkById(200, function (Collection $rows) use ($now, &$finalized) {
                foreach ($rows as $row) {
                    if ($this->advance($row, $now)) {
                        $finalized++;
                    }
                }
            });

        return ['created' => $created, 'finalized' => $finalized];
    }

    /** Lịch hôm nay đã tới giờ mà chưa có dòng điểm danh → tạo, chụp lại giờ + mốc học thực. */
    private function openDueSessions(Carbon $now): int
    {
        $created = 0;

        StudySchedule::query()
            ->where('weekday', $now->isoWeekday())
            ->whereHas('student', fn ($q) => $q->where('status', User::STATUS_ACTIVE))
            ->whereNotIn('student_id', StudentAttendance::whereDate('attendance_date', $now->toDateString())->select('student_id'))
            ->each(function (StudySchedule $slot) use ($now, &$created) {
                $start = $slot->startsOn($now->copy()->startOfDay());

                if ($now->lt($start)) {
                    return;
                }

                $end = $slot->endsOn($now->copy()->startOfDay());

                StudentAttendance::firstOrCreate(
                    ['student_id' => $slot->student_id, 'attendance_date' => $now->toDateString()],
                    [
                        'scheduled_start' => $start,
                        'scheduled_end' => $end,
                        'scheduled_minutes' => $slot->duration_minutes,
                        // Lệnh chạy trễ quá cả khung giờ (máy chủ tắt) thì không biết mốc → tính cả ngày, chấp nhận lệch.
                        'baseline_active_seconds' => $now->lt($end) ? $this->activeSince($slot->student_id, $start) : 0,
                    ],
                );
                $created++;
            });

        return $created;
    }

    /** Cập nhật một buổi chưa chốt. Trả true nếu vừa chốt. */
    private function advance(StudentAttendance $row, Carbon $now): bool
    {
        $student = $row->student;

        if (! $student) {
            $row->delete();

            return false;
        }

        $active = max(0, $this->activeSince($student->id, $row->scheduled_start) - $row->baseline_active_seconds);
        $row->active_seconds = $active;

        $justEntered = false;
        if (! $row->entered_at && $active > 0) {
            $row->entered_at = $now;
            $row->late_minutes = max(0, (int) floor($row->scheduled_start->diffInMinutes($now, false)));
            $justEntered = true;
        }

        if ($now->gte($row->scheduled_end)) {
            $this->finalize($row, $student);

            return true;
        }

        $minutesIn = (int) floor($row->scheduled_start->diffInMinutes($now, false));

        $row->status = match (true) {
            $row->entered_at !== null => StudentAttendance::STATUS_IN_PROGRESS,
            $minutesIn >= self::NOT_STARTED_AFTER_MINUTES => StudentAttendance::STATUS_ABSENT_PENDING,
            $minutesIn >= self::LATE_AFTER_MINUTES => StudentAttendance::STATUS_LATE,
            default => StudentAttendance::STATUS_PENDING,
        };
        // Đặc tả: "Có vào nhưng không active trong 10 phút → đánh dấu nguy cơ bỏ buổi" — chỉ đánh dấu, không báo:
        // trẻ ngồi đọc đề lâu cũng không có thao tác, báo ngay dễ thành báo động giả.
        if ($row->status === StudentAttendance::STATUS_IN_PROGRESS && ! $row->idle_flagged_at) {
            $lastActive = StudentDailyActivity::where('user_id', $student->id)->max('last_active_at');

            if ($lastActive && Carbon::parse($lastActive)->lt($now->copy()->subMinutes(self::IDLE_FLAG_MINUTES))) {
                $row->idle_flagged_at = $now;
            }
        }

        $row->save();

        // TA-13: báo con vào học — chỉ phụ huynh đã bật (mặc định tắt).
        if ($justEntered) {
            $this->notifyParents($student, $row, ChildAttendanceAlert::STARTED, optInOnly: true);
        }

        // Đặc tả: "Không vào học sau 15 phút từ giờ học → gửi thông báo phụ huynh". Một lần mỗi buổi.
        if ($row->status === StudentAttendance::STATUS_ABSENT_PENDING && ! $row->not_started_notified_at) {
            $this->notifyParents($student, $row, ChildAttendanceAlert::NOT_STARTED);
            $row->forceFill(['not_started_notified_at' => $now])->save();
        }

        return false;
    }

    private function finalize(StudentAttendance $row, User $student): void
    {
        $row->quiz_submitted = StudySession::query()
            ->where('user_id', $student->id)
            ->whereBetween('quiz_submitted_at', [$row->scheduled_start, $row->scheduled_end->copy()->addMinutes(30)])
            ->exists();

        $hasPath = LearningPath::where('user_id', $student->id)->where('status', LearningPath::STATUS_ACTIVE)->exists();
        $enoughTime = $row->active_seconds >= $row->scheduled_minutes * 60 * self::PRESENT_ACTIVE_PERCENT / 100;

        $row->status = match (true) {
            $row->active_seconds < self::ABSENT_BELOW_MINUTES * 60 => StudentAttendance::STATUS_ABSENT,
            $enoughTime && ($row->quiz_submitted || ! $hasPath) => StudentAttendance::STATUS_PRESENT,
            default => StudentAttendance::STATUS_PARTIAL,
        };
        $row->finalized_at = now();
        $row->save();

        // TA-10 bước 4: chốt vắng → báo phụ huynh kèm số buổi vắng liên tiếp và buổi học kế tiếp để học bù.
        if ($row->status === StudentAttendance::STATUS_ABSENT && ! $row->absent_notified_at) {
            $this->notifyParents($student, $row, ChildAttendanceAlert::ABSENT);
            $row->forceFill(['absent_notified_at' => now()])->save();

            return;
        }

        // TA-13: báo học xong buổi (có mặt / học chưa đủ) — chỉ phụ huynh đã bật.
        $this->notifyParents($student, $row, ChildAttendanceAlert::FINISHED, optInOnly: true);

        // TA-12: học đủ giờ nhưng bỏ kiểm tra cuối buổi → "chưa hoàn thành".
        if ($row->status === StudentAttendance::STATUS_PARTIAL && $enoughTime && $hasPath && ! $row->quiz_submitted) {
            $this->notifyParents($student, $row, ChildAttendanceAlert::QUIZ_MISSED);
        }

        // TA-12: điểm kiểm tra cuối buổi giảm 3 buổi liên tiếp → đề xuất phụ huynh theo dõi. Mỗi buổi chốt một lần nên không trùng.
        if ($row->quiz_submitted && $this->quizDeclining($student)) {
            $this->notifyParents($student, $row, ChildAttendanceAlert::QUIZ_DECLINE);
        }
    }

    /** Ba bài kiểm tra cuối buổi gần nhất có điểm giảm dần ngặt (bài mới nhất thấp nhất). */
    public function quizDeclining(User $student): bool
    {
        $last = StudySession::query()
            ->where('user_id', $student->id)
            ->whereNotNull('quiz_percent')
            ->latest('quiz_submitted_at')
            ->limit(self::DECLINE_SESSIONS)
            ->pluck('quiz_percent')
            ->all();

        if (count($last) < self::DECLINE_SESSIONS) {
            return false;
        }

        for ($i = 0; $i < count($last) - 1; $i++) {
            if ($last[$i] >= $last[$i + 1]) {
                return false;
            }
        }

        return true;
    }

    /** Số buổi vắng liên tiếp tính tới buổi gần nhất (dừng ở buổi đầu tiên không vắng). */
    public function consecutiveAbsences(User $student): int
    {
        $count = 0;

        foreach (StudentAttendance::where('student_id', $student->id)
            ->whereIn('status', StudentAttendance::FINAL_STATUSES)
            ->latest('attendance_date')->limit(30)->pluck('status') as $status) {
            if ($status !== StudentAttendance::STATUS_ABSENT) {
                break;
            }
            $count++;
        }

        return $count;
    }

    /** Buổi theo lịch kế tiếp SAU thời điểm $after — gợi ý giờ học bù cho phụ huynh. */
    public function nextSlotAfter(User $student, Carbon $after): ?Carbon
    {
        $slots = StudySchedule::where('student_id', $student->id)->get();

        if ($slots->isEmpty()) {
            return null;
        }

        for ($i = 0; $i <= 7; $i++) {
            $day = $after->copy()->startOfDay()->addDays($i);
            $slot = $slots->firstWhere('weekday', $day->isoWeekday());

            if ($slot && $slot->startsOn($day)->gt($after)) {
                return $slot->startsOn($day);
            }
        }

        return null;
    }

    /** @return Collection<int, StudentAttendance> N buổi gần nhất, mới nhất trước. */
    public function recent(User $student, int $limit = 14): Collection
    {
        return StudentAttendance::where('student_id', $student->id)->latest('attendance_date')->limit($limit)->get();
    }

    /** Tổng giây học thực từ ngày của $since trở đi (thời gian học thật lưu theo ngày). */
    private function activeSince(int $studentId, Carbon $since): int
    {
        return (int) StudentDailyActivity::where('user_id', $studentId)
            ->whereDate('activity_date', '>=', $since->toDateString())
            ->sum('active_seconds');
    }

    /** @param  bool  $optInOnly  chỉ gửi phụ huynh đã bật "báo khi con bắt đầu / học xong buổi" */
    private function notifyParents(User $student, StudentAttendance $row, string $kind, bool $optInOnly = false): void
    {
        $parents = $student->linkedParents()->with('parentProfile')->get()
            ->when($optInOnly, fn ($c) => $c->filter(fn (User $p) => (bool) $p->parentProfile?->session_events_enabled));

        if ($parents->isEmpty()) {
            return;
        }

        $streak = $kind === ChildAttendanceAlert::ABSENT ? $this->consecutiveAbsences($student) : 0;
        $next = $kind === ChildAttendanceAlert::ABSENT ? $this->nextSlotAfter($student, $row->scheduled_end) : null;

        DB::afterCommit(function () use ($parents, $student, $row, $kind, $streak, $next) {
            foreach ($parents as $parent) {
                $parent->notify(new ChildAttendanceAlert($student, $row, $kind, $streak, $next));
            }
        });
    }
}
