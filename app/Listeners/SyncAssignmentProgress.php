<?php

namespace App\Listeners;

use App\Events\ExamAttemptFinished;
use App\Events\LessonCompleted;
use App\Services\Teaching\AssignmentProgressService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Bài giao dạng "đề kiểm tra" và "học bài" không có form nộp riêng —
 * tiến độ được suy ra từ việc học sinh làm đề / học xong bài.
 *
 * Chạy sau commit: nếu transaction nộp bài rollback thì không cập nhật nhầm.
 */
class SyncAssignmentProgress implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly AssignmentProgressService $progress) {}

    public function handleExamAttemptFinished(ExamAttemptFinished $event): void
    {
        $this->progress->syncFromExamAttempt($event->attempt);
    }

    public function handleLessonCompleted(LessonCompleted $event): void
    {
        $this->progress->syncFromLesson($event->student, $event->lesson);
    }
}
