<?php

namespace App\Policies;

use App\Models\Question;
use App\Models\User;

class QuestionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('question.view');
    }

    public function view(User $user, Question $question): bool
    {
        return $user->hasPermission('question.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('question.create');
    }

    public function update(User $user, Question $question): bool
    {
        return $user->hasPermission('question.update') && $question->created_by === $user->id;
    }

    public function delete(User $user, Question $question): bool
    {
        return $user->hasPermission('question.delete') && $question->created_by === $user->id;
    }
}
