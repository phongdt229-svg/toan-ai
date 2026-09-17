<?php

namespace App\Policies;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\SchoolClass;
use App\Models\User;

class AssignmentPolicy
{
    /** Giao bài vào lớp mình dạy. */
    public function create(User $user, SchoolClass $class): bool
    {
        return $user->hasPermission('assignment.manage') && $class->hasTeacher($user);
    }

    public function manage(User $user, Assignment $assignment): bool
    {
        return $user->hasPermission('assignment.manage')
            && $assignment->schoolClass()->withTrashed()->first()?->hasTeacher($user) === true;
    }

    /** Học sinh chỉ xem/làm bài được giao cho chính mình. */
    public function work(User $user, Assignment $assignment): bool
    {
        return $user->hasPermission('assignment.submit')
            && AssignmentStudent::where('assignment_id', $assignment->id)->where('student_id', $user->id)->exists();
    }
}
