<?php

namespace App\Console\Commands;

use App\Services\Learning\ExamService;
use Illuminate\Console\Command;

/**
 * Chốt các lượt làm đề đã quá giờ mà học sinh không nộp (đóng tab, mất mạng).
 * Không có lệnh này, lượt treo mãi ở `in_progress` và không có điểm.
 */
class FinalizeExpiredExamAttempts extends Command
{
    protected $signature = 'exams:finalize-expired';

    protected $description = 'Tự động nộp các lượt làm đề đã hết giờ';

    public function handle(ExamService $exams): int
    {
        $count = $exams->finalizeExpired();

        $this->info("Đã tự nộp {$count} lượt làm bài.");

        return self::SUCCESS;
    }
}
