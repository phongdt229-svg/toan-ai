<?php

namespace App\Policies;

use App\Models\QaAnswer;
use App\Models\Role;
use App\Models\User;

/** Quyền trên từng câu trả lời. Admin đã được `Gate::before` cho qua. */
class QaAnswerPolicy
{
    /** Báo xấu nội dung của người khác — báo bài của chính mình thì vô nghĩa. */
    public function report(User $user, QaAnswer $answer): bool
    {
        return $answer->user_id !== $user->id;
    }

    public function moderate(User $user): bool
    {
        return $user->hasRole(Role::TEACHER) || $user->hasRole(Role::ADMIN);
    }
}
