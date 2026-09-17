<?php

namespace App\Policies;

use App\Models\ExamAttempt;
use App\Models\User;

class ExamAttemptPolicy
{
    /** Chỉ chủ lượt làm được xem/làm tiếp — id lượt nằm trên URL nên phải chặn đoán id. */
    public function view(User $user, ExamAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id;
    }

    public function answer(User $user, ExamAttempt $attempt): bool
    {
        return $attempt->user_id === $user->id && $attempt->isInProgress();
    }
}
