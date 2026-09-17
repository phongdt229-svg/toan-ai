<?php

namespace App\Listeners;

use App\Events\LessonCompleted;
use App\Events\MasteryUpdated;
use App\Services\Learning\RecommendationService;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Đề xuất phải phản ánh bài vừa làm: học xong bài được gợi ý thì gợi ý biến mất,
 * vừa sai nhiều ở chủ đề nào thì chủ đề đó lên đầu.
 */
class RefreshRecommendations implements ShouldHandleEventsAfterCommit
{
    public function __construct(private readonly RecommendationService $recommendations) {}

    public function handleMasteryUpdated(MasteryUpdated $event): void
    {
        if ($event->student->isStudent()) {
            $this->recommendations->refresh($event->student);
        }
    }

    public function handleLessonCompleted(LessonCompleted $event): void
    {
        $this->recommendations->refresh($event->student);
    }
}
