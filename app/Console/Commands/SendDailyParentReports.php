<?php

namespace App\Console\Commands;

use App\Services\Parenting\DailyReportService;
use Illuminate\Console\Command;

/** Báo cáo cuối ngày cho phụ huynh đã bật (TA-13) — 21:30, sau giờ học buổi tối. */
class SendDailyParentReports extends Command
{
    protected $signature = 'reports:daily-parents';

    protected $description = 'Gửi báo cáo học tập cuối ngày cho phụ huynh đã bật';

    public function handle(DailyReportService $reports): int
    {
        $this->info('Đã gửi báo cáo cuối ngày cho '.$reports->sendAll().' phụ huynh.');

        return self::SUCCESS;
    }
}
