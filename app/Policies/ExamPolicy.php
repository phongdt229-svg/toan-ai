<?php

namespace App\Policies;

use App\Models\Exam;
use App\Models\User;

class ExamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('exam.create');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('exam.create');
    }

    public function update(User $user, Exam $exam): bool
    {
        return $user->hasPermission('exam.update') && $exam->created_by === $user->id;
    }

    public function delete(User $user, Exam $exam): bool
    {
        return $user->hasPermission('exam.delete') && $exam->created_by === $user->id;
    }

    /** Giáo viên chỉ chấm bài của đề do mình ra. */
    public function grade(User $user, Exam $exam): bool
    {
        return $user->hasPermission('exam.grade') && $exam->created_by === $user->id;
    }

    public function take(User $user, Exam $exam): bool
    {
        return $user->hasPermission('exam.take') && $exam->isPublished();
    }
}
