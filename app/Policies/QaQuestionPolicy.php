<?php

namespace App\Policies;

use App\Models\QaAnswer;
use App\Models\QaQuestion;
use App\Models\Role;
use App\Models\User;

/**
 * Ai làm gì được trong mục Hỏi đáp. Admin đã được `Gate::before` cho qua hết.
 *
 * Nguyên tắc: đọc và trả lời thì ai đăng nhập cũng được (giá trị của hỏi đáp nằm ở chỗ
 * bạn cùng lứa giải thích cho nhau); kiểm duyệt thì chỉ giáo viên và quản trị.
 */
class QaQuestionPolicy
{
    public function view(User $user, QaQuestion $question): bool
    {
        // Nội dung đang ẩn: chỉ người viết và người kiểm duyệt còn thấy.
        return ! $question->isHidden() || $question->user_id === $user->id || $this->moderates($user);
    }

    public function create(User $user): bool
    {
        return $user->isStudent() || $user->isTeacher();
    }

    public function answer(User $user, QaQuestion $question): bool
    {
        return ! $question->isHidden() && ($user->isStudent() || $user->isTeacher());
    }

    /** Chọn lời giải: người hỏi hoặc giáo viên. */
    public function accept(User $user, QaQuestion $question): bool
    {
        return $question->user_id === $user->id || $this->moderates($user);
    }

    /** Chỉ sửa được câu hỏi của mình và khi chưa ai trả lời (luật ở QaService). */
    public function update(User $user, QaQuestion $question): bool
    {
        return $question->user_id === $user->id && ! $question->isHidden();
    }

    public function moderate(User $user): bool
    {
        return $this->moderates($user);
    }

    /** Báo xấu nội dung của người khác — báo bài của chính mình thì vô nghĩa. */
    public function report(User $user, QaQuestion|QaAnswer $target): bool
    {
        return $target->user_id !== $user->id;
    }

    private function moderates(User $user): bool
    {
        return $user->hasRole(Role::TEACHER) || $user->hasRole(Role::ADMIN);
    }
}
