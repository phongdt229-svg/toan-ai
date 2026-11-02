<?php

namespace App\Console\Commands;

use App\Models\Role;
use App\Models\StudySchedule;
use App\Models\User;
use App\Notifications\ChildScheduleReminder;
use Illuminate\Console\Command;

/**
 * Nhắc phụ huynh lịch học hôm nay của con (đặc tả module 9).
 *
 * Chạy buổi sáng. Chỉ nhắc khi hôm nay con CÓ khung giờ học theo lịch; mỗi phụ huynh tối đa một thông báo/ngày,
 * gom mọi con. Phụ huynh tắt loại thông báo này trong Cài đặt thì `via()` trả rỗng.
 */
class RemindParentSchedule extends Command
{
    protected $signature = 'parents:remind-schedule';

    protected $description = 'Nhắc phụ huynh lịch học hôm nay của con';

    public function handle(): int
    {
        $weekday = today()->dayOfWeekIso; // 1 = Thứ 2 … 7 = Chủ nhật, khớp StudySchedule::WEEKDAYS
        $sent = 0;

        User::query()
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('roles', fn ($q) => $q->where('name', Role::PARENT))
            ->whereDoesntHave('notifications', fn ($q) => $q->where('type', ChildScheduleReminder::class)->whereDate('created_at', today()))
            ->chunkById(200, function ($parents) use ($weekday, &$sent) {
                foreach ($parents as $parent) {
                    $children = $parent->linkedChildren()->get();

                    if ($children->isEmpty()) {
                        continue;
                    }

                    $slots = StudySchedule::query()
                        ->whereIn('student_id', $children->pluck('id'))
                        ->where('weekday', $weekday)
                        ->orderBy('start_time')
                        ->get()
                        ->groupBy('student_id');

                    $lines = [];
                    foreach ($children as $child) {
                        if ($slots->has($child->id)) {
                            $lines[] = [
                                'name' => $child->name,
                                'slots' => $slots[$child->id]->map(fn (StudySchedule $s) => [
                                    'start' => $s->startLabel(),
                                    'minutes' => $s->duration_minutes,
                                ])->all(),
                            ];
                        }
                    }

                    if ($lines !== []) {
                        $parent->notify(new ChildScheduleReminder($lines));
                        $sent++;
                    }
                }
            });

        $this->info("Đã nhắc {$sent} phụ huynh.");

        return self::SUCCESS;
    }
}
