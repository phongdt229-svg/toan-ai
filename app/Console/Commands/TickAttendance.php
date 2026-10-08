<?php

namespace App\Console\Commands;

use App\Services\Learning\AttendanceService;
use Illuminate\Console\Command;

/** Điểm danh theo lịch học (TA-09/TA-10) — chạy mỗi phút; mọi luật nằm ở AttendanceService. */
class TickAttendance extends Command
{
    protected $signature = 'attendance:tick';

    protected $description = 'Mở / cập nhật / chốt điểm danh các buổi học theo lịch, báo phụ huynh khi con chưa vào hoặc vắng';

    public function handle(AttendanceService $attendance): int
    {
        $result = $attendance->tick();

        $this->info("Mở {$result['created']} buổi, chốt {$result['finalized']} buổi.");

        return self::SUCCESS;
    }
}
