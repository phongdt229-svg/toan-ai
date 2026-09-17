<?php

namespace App\Events;

use App\Models\Lesson;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Học sinh vừa hoàn thành bài học (lần đầu chuyển sang completed).
 */
class LessonCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly User $student,
        public readonly Lesson $lesson,
    ) {}
}
