<?php

namespace App\Events;

use App\Models\ExamAttempt;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Lượt làm đề vừa nộp, hoặc điểm vừa thay đổi do chấm tay tự luận.
 */
class ExamAttemptFinished
{
    use Dispatchable;

    public function __construct(public readonly ExamAttempt $attempt) {}
}
