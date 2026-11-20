<?php

namespace App\Listeners;

use App\Events\ExamAttemptFinished;
use App\Events\LessonCompleted;
use App\Events\StudySessionCompleted;
use App\Services\Rewards\RewardService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Cộng điểm thưởng từ việc học thật. Chạy sau commit: nộp bài rollback thì không cộng nhầm.
 * Gọi lại nhiều lần vô hại — RewardService chỉ cộng một lần cho mỗi nguồn.
 */
class AwardLearningPoints implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly RewardService $rewards) {}

    public function handleLessonCompleted(LessonCompleted $event): void
    {
        $this->rewards->forLesson($event->student, $event->lesson);
    }

    public function handleExamAttemptFinished(ExamAttemptFinished $event): void
    {
        $this->rewards->forExamAttempt($event->attempt);
    }

    public function handleStudySessionCompleted(StudySessionCompleted $event): void
    {
        $this->rewards->forStudySession($event->student, $event->session);
    }
}
