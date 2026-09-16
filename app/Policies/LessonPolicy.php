<?php

namespace App\Policies;

use App\Models\Lesson;
use App\Models\User;

/**
 * Permission trả lời "được làm hành động này không", Policy trả lời
 * "được làm trên bản ghi CỤ THỂ này không". Admin đã được Gate::before cho qua.
 */
class LessonPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('lesson.view');
    }

    public function view(User $user, Lesson $lesson): bool
    {
        return $user->hasPermission('lesson.view')
            && ($lesson->isPublished() || $this->owns($user, $lesson));
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('lesson.create');
    }

    public function update(User $user, Lesson $lesson): bool
    {
        return $user->hasPermission('lesson.update') && $this->owns($user, $lesson);
    }

    public function delete(User $user, Lesson $lesson): bool
    {
        return $user->hasPermission('lesson.delete') && $this->owns($user, $lesson);
    }

    public function publish(User $user, Lesson $lesson): bool
    {
        return $user->hasPermission('lesson.publish') && $this->owns($user, $lesson);
    }

    /** Giáo viên chỉ sửa bài do chính mình tạo. */
    private function owns(User $user, Lesson $lesson): bool
    {
        return $lesson->created_by === $user->id;
    }
}
