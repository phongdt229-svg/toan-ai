<?php

namespace App\Policies;

use App\Models\SchoolClass;
use App\Models\User;

/**
 * Giáo viên chủ nhiệm và giáo viên phụ đều quản lý được lớp;
 * chỉ chủ nhiệm được thêm giáo viên phụ, đổi mã lớp hoặc lưu trữ lớp.
 */
class SchoolClassPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('class.manage');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('class.manage');
    }

    public function view(User $user, SchoolClass $class): bool
    {
        return $user->hasPermission('class.manage') && $class->hasTeacher($user);
    }

    public function manageStudents(User $user, SchoolClass $class): bool
    {
        return $this->view($user, $class);
    }

    public function update(User $user, SchoolClass $class): bool
    {
        return $user->hasPermission('class.manage') && $class->isOwnedBy($user);
    }

    public function delete(User $user, SchoolClass $class): bool
    {
        return $this->update($user, $class);
    }
}
