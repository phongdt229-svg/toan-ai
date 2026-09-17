<?php

namespace App\Listeners;

use App\Events\ExamAttemptFinished;
use App\Events\LessonCompleted;
use App\Events\MasteryUpdated;
use App\Services\Learning\LearningPathService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Lộ trình tự điều chỉnh (§35): học xong bài / luyện đủ / làm đề → mục tương ứng tự đánh dấu xong;
 * chủ đề đã học mà tụt điểm → chèn ôn tập.
 */
class SyncLearningPath implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly LearningPathService $paths) {}

    public function handleLessonCompleted(LessonCompleted $event): void
    {
        $this->paths->syncLessonCompleted($event->student, $event->lesson);
    }

    public function handleExamAttemptFinished(ExamAttemptFinished $event): void
    {
        $this->paths->syncExamFinished($event->attempt);
    }

    public function handleMasteryUpdated(MasteryUpdated $event): void
    {
        if ($event->student->isStudent()) {
            $this->paths->syncMastery($event->student);
        }
    }
}
