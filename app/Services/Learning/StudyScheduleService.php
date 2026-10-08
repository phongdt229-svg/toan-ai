<?php

namespace App\Services\Learning;

use App\Models\StudySchedule;
use App\Models\User;
use App\Notifications\StudyScheduleChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Lịch học theo tuần (D-01). Học sinh tự đặt; phụ huynh đã liên kết cũng sửa được.
 * Ai sửa thì bên còn lại được báo — lịch là cam kết hai bên, không để một bên đổi lặng lẽ.
 */
class StudyScheduleService
{
    /** @return Collection<int, StudySchedule> keyBy weekday */
    public function forStudent(User $student): Collection
    {
        return StudySchedule::where('student_id', $student->id)->orderBy('weekday')->get()->keyBy('weekday');
    }

    public function slotOn(User $student, Carbon $date): ?StudySchedule
    {
        return StudySchedule::where('student_id', $student->id)->where('weekday', $date->isoWeekday())->first();
    }

    /**
     * Thay toàn bộ lịch tuần. Ngày không có trong $slots = không học ngày đó.
     *
     * @param  array<int, array{start_time: string, duration_minutes: int}>  $slots  weekday => khung giờ
     */
    public function replace(User $student, array $slots, User $editor): Collection
    {
        DB::transaction(function () use ($student, $slots, $editor) {
            StudySchedule::where('student_id', $student->id)->whereNotIn('weekday', array_keys($slots))->delete();

            foreach ($slots as $weekday => $slot) {
                StudySchedule::updateOrCreate(
                    ['student_id' => $student->id, 'weekday' => (int) $weekday],
                    [
                        'start_time' => $slot['start_time'],
                        'duration_minutes' => (int) $slot['duration_minutes'],
                        'updated_by' => $editor->id,
                    ],
                );
            }
        });

        $schedule = $this->forStudent($student);

        // Báo cho bên KHÔNG bấm lưu: phụ huynh sửa → báo con; con sửa → báo các phụ huynh đã liên kết.
        $recipients = $editor->is($student)
            ? $student->linkedParents()->get()
            : collect([$student]);

        foreach ($recipients as $recipient) {
            $recipient->notify(new StudyScheduleChanged($student, $editor, $schedule->count()));
        }

        return $schedule;
    }

    /** Tổng phút học dự kiến trong tuần — hiện ở trang lịch để phụ huynh thấy có quá tải không. */
    public function weeklyMinutes(Collection $schedule): int
    {
        return (int) $schedule->sum('duration_minutes');
    }
}
