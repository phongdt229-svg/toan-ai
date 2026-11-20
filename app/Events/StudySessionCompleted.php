<?php

namespace App\Events;

use App\Models\StudySession;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;

/** Một buổi trong lộ trình vừa xong (nộp kiểm tra cuối buổi, hoặc buổi không có câu kiểm tra). */
class StudySessionCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly User $student,
        public readonly StudySession $session,
    ) {}
}
