<?php

namespace Database\Seeders;

use App\Models\ParentChild;
use App\Models\StudentActivityLog;
use App\Models\StudentAttendance;
use App\Models\StudentDailyActivity;
use App\Models\StudySchedule;
use App\Models\User;
use App\Services\Learning\AttendanceService;
use App\Services\Parenting\ChildLinkService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dữ liệu mẫu cho các tính năng giám sát (lịch học, thời gian học thật, điểm danh, streak, rủi ro) — chỉ local.
 *
 * Không gọi heartbeat thật mà ghi thẳng tổng theo ngày: 21 ngày heartbeat là ~25.000 request.
 * Trạng thái điểm danh chấm theo ĐÚNG luật của AttendanceService (cùng hằng số), không bịa màu.
 * Chạy lại an toàn: học sinh nào đã có lịch học thì bỏ qua.
 */
class SampleMonitoringSeeder extends Seeder
{
    private const DAYS = 21;

    public function run(): void
    {
        $student = User::where('email', 'student@gmail.com')->first();
        $parent = User::where('email', 'parent@gmail.com')->first();

        if (! $student) {
            return;
        }

        mt_srand(20261021);

        // Phụ huynh demo theo dõi học sinh demo — trang "Con của tôi" mới có gì để xem.
        if ($parent && $student->studentProfile && ! ParentChild::where('parent_id', $parent->id)->where('student_id', $student->id)->exists()) {
            app(ChildLinkService::class)->ensureProfile($parent);
            app(ChildLinkService::class)->linkByCode($parent, $student->studentProfile->link_code);
        }

        $student->studentProfile?->update([
            'interests' => $student->studentProfile->interests ?: ['bóng đá', 'game'],
            'target_score' => $student->studentProfile->target_score ?? 8,
        ]);

        // Học sinh demo: chăm, đang có chuỗi ngày học. hs01–hs10: đủ kiểu để bảng rủi ro của giáo viên có xanh/vàng/đỏ.
        $this->seedStudent($student, [1, 3, 5], '19:30', 60, diligence: 0.9);

        foreach (User::where('email', 'like', 'hs%@%')->orderBy('email')->limit(10)->get() as $i => $hs) {
            $days = [[1, 3, 5], [2, 4, 6], [1, 4], [2, 5, 7], [1, 2, 3, 4, 5]][$i % 5];
            $this->seedStudent($hs, $days, ['18:00', '19:00', '20:00'][$i % 3], [45, 60, 90][$i % 3], diligence: [0.95, 0.8, 0.6, 0.4, 0.2][$i % 5]);
        }
    }

    /** @param  array<int, int>  $weekdays  ISO 1 = Thứ 2 */
    private function seedStudent(User $student, array $weekdays, string $start, int $minutes, float $diligence): void
    {
        if (StudySchedule::where('student_id', $student->id)->exists()) {
            return;
        }

        foreach ($weekdays as $weekday) {
            StudySchedule::create([
                'student_id' => $student->id, 'weekday' => $weekday,
                'start_time' => $start, 'duration_minutes' => $minutes, 'updated_by' => $student->id,
            ]);
        }

        for ($d = self::DAYS; $d >= 1; $d--) {
            $day = today()->subDays($d);
            $scheduled = in_array($day->isoWeekday(), $weekdays, true);

            // Ngày có lịch: chăm thì học gần đủ giờ; ngày không lịch thỉnh thoảng vẫn học thêm chút.
            $studies = mt_rand(0, 100) / 100 < ($scheduled ? $diligence : $diligence / 3);
            $active = $studies ? (int) round(($scheduled ? $minutes : 20) * mt_rand(55, 110) / 100) : (mt_rand(0, 3) === 0 ? mt_rand(1, 4) : 0);
            $online = $active > 0 ? (int) round($active * mt_rand(110, 170) / 100) : 0;

            if ($online > 0) {
                StudentDailyActivity::create([
                    'user_id' => $student->id, 'activity_date' => $day->toDateString(),
                    'online_seconds' => $online * 60, 'active_seconds' => $active * 60,
                    'first_seen_at' => $day->copy()->setTimeFromTimeString($start)->subMinutes(5),
                    'last_seen_at' => $day->copy()->setTimeFromTimeString($start)->addMinutes($online),
                    'last_active_at' => $day->copy()->setTimeFromTimeString($start)->addMinutes($active),
                ]);
                $this->logs($student, $day->copy()->setTimeFromTimeString($start));
            }

            if ($scheduled) {
                $this->attendance($student, $day, $start, $minutes, $active);
            }
        }
    }

    /** Chấm như AttendanceService: ≥ 70% thời lượng = có mặt, < 2 phút = vắng, còn lại học chưa đủ. */
    private function attendance(User $student, Carbon $day, string $start, int $minutes, int $active): void
    {
        $begin = $day->copy()->setTimeFromTimeString($start);
        $late = $active > 0 ? [0, 0, 0, 5, 12, 20][mt_rand(0, 5)] : null;

        $status = match (true) {
            $active < AttendanceService::ABSENT_BELOW_MINUTES => StudentAttendance::STATUS_ABSENT,
            $active >= $minutes * AttendanceService::PRESENT_ACTIVE_PERCENT / 100 => StudentAttendance::STATUS_PRESENT,
            default => StudentAttendance::STATUS_PARTIAL,
        };

        StudentAttendance::create([
            'student_id' => $student->id, 'attendance_date' => $day->toDateString(),
            'scheduled_start' => $begin, 'scheduled_end' => $begin->copy()->addMinutes($minutes),
            'scheduled_minutes' => $minutes, 'status' => $status,
            'active_seconds' => $active * 60,
            'entered_at' => $late !== null ? $begin->copy()->addMinutes($late) : null,
            'late_minutes' => $late,
            // Học sinh chưa có lộ trình thì "có mặt" không đòi kiểm tra cuối buổi — đây chỉ để bảng hiện cột cho đẹp.
            'quiz_submitted' => $status === StudentAttendance::STATUS_PRESENT,
            'finalized_at' => $begin->copy()->addMinutes($minutes),
        ]);
    }

    private function logs(User $student, Carbon $at): void
    {
        foreach (['lesson_open', 'section_view', 'exercise_start', 'tab_inactive'] as $i => $type) {
            if ($type === 'tab_inactive' && mt_rand(0, 2) > 0) {
                continue;
            }
            StudentActivityLog::create([
                'user_id' => $student->id, 'event_type' => $type,
                'path' => '/hoc-sinh/bai-hoc/so-sanh-hai-phan-so', 'occurred_at' => $at->copy()->addMinutes($i * 7),
            ]);
        }
    }
}
