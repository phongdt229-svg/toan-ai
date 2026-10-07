<?php

namespace App\Console\Commands;

use App\Services\Learning\ExamService;
use App\Services\Learning\LearningPathService;
use App\Services\Learning\PlacementTestService;
use Illuminate\Console\Command;

/**
 * Chốt các lượt làm bài có bấm giờ đã quá giờ mà học sinh không nộp (đóng tab, mất mạng):
 * đề kiểm tra (§16), kiểm tra đầu vào (§34) và kiểm tra cuối buổi 15 phút.
 * Không có lệnh này, lượt treo mãi ở `in_progress` và không có điểm.
 */
class FinalizeExpiredExamAttempts extends Command
{
    protected $signature = 'exams:finalize-expired';

    protected $description = 'Tự động nộp các lượt làm bài đã hết giờ';

    public function handle(ExamService $exams, PlacementTestService $placements, LearningPathService $paths): int
    {
        $count = $exams->finalizeExpired() + $placements->finalizeExpired() + $paths->finalizeExpiredQuizzes();

        $this->info("Đã tự nộp {$count} lượt làm bài.");

        return self::SUCCESS;
    }
}
