<?php

namespace App\Console\Commands;

use App\Jobs\SendWeeklyParentReport;
use App\Models\ParentChild;
use App\Models\User;
use Illuminate\Console\Command;

class SendWeeklyParentReports extends Command
{
    protected $signature = 'reports:weekly-parents';

    protected $description = 'Xếp hàng gửi email báo cáo học tập tuần cho phụ huynh (§14)';

    public function handle(): int
    {
        $count = 0;

        User::query()
            ->whereHas('parentProfile', fn ($q) => $q->where('weekly_report_enabled', true))
            ->whereHas('children', fn ($q) => $q->where('parent_children.status', ParentChild::STATUS_LINKED))
            ->select('id')
            ->chunkById(200, function ($parents) use (&$count) {
                foreach ($parents as $parent) {
                    SendWeeklyParentReport::dispatch($parent->id);
                    $count++;
                }
            });

        $this->info("Đã xếp hàng {$count} báo cáo tuần.");

        return self::SUCCESS;
    }
}
