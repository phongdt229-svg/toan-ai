<?php

namespace App\Services\AI;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;

/**
 * Khi nào AI Tutor được phép giúp — để AI "giúp hiểu", không thành máy giải bài hộ (§10).
 *
 * 1. Đang làm đề kiểm tra → khoá toàn bộ AI (kể cả chat tự do: dán đề vào là ra đáp án).
 * 2. Câu thuộc bài giao chưa nộp → chỉ được Gợi ý.
 * 3. Câu thuộc đề mà học sinh chưa được xem đáp án → chỉ được Gợi ý.
 * 4. Giải thích / Phân tích lỗi → phải tự làm câu đó ít nhất một lần trước.
 */
class TutorAccessGuard
{
    public const REQUIRES_ATTEMPT = ['explain', 'analyze_mistake'];

    /** @throws TutorBlockedException */
    public function ensureCanUseTutor(User $user): void
    {
        if (! $user->hasPermission('ai.tutor') && ! $user->isAdmin()) {
            throw new TutorBlockedException('Tài khoản của bạn không dùng được AI Tutor.');
        }

        if ($user->isStudent() && $this->hasExamInProgress($user)) {
            throw new TutorBlockedException('AI Tutor tạm khoá trong lúc bạn làm bài kiểm tra. Nộp bài xong là dùng lại được.');
        }
    }

    /** @throws TutorBlockedException */
    public function ensureQuestionMode(User $user, Question $question, string $mode): void
    {
        if (! $user->isStudent() || in_array($mode, ['hint', 'chat'], true)) {
            return;
        }

        if ($this->inPendingAssignment($user, $question)) {
            throw new TutorBlockedException('Câu này thuộc bài được giao chưa nộp — AI chỉ gợi ý được thôi. Nộp bài xong em hỏi giải thích nhé.');
        }

        if ($this->inExamWithHiddenAnswers($user, $question)) {
            throw new TutorBlockedException('Đáp án của đề này chưa được công bố nên AI chỉ gợi ý được.');
        }

        if (in_array($mode, self::REQUIRES_ATTEMPT, true) && ! $this->hasAttempted($user, $question)) {
            throw new TutorBlockedException('Em thử tự làm câu này trước đã, rồi AI sẽ giải thích chỗ em còn vướng.');
        }
    }

    public function hasExamInProgress(User $user): bool
    {
        return ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', ExamAttempt::STATUS_IN_PROGRESS)
            ->where('expires_at', '>', now()->subSeconds(ExamAttempt::GRACE_SECONDS))
            ->exists();
    }

    private function inPendingAssignment(User $user, Question $question): bool
    {
        return AssignmentStudent::query()
            ->where('student_id', $user->id)
            ->where('status', AssignmentStudent::STATUS_ASSIGNED)
            ->whereHas('assignment', fn ($q) => $q
                ->whereNull('deleted_at')
                ->where('status', Assignment::STATUS_PUBLISHED)
                ->whereHas('questions', fn ($qq) => $qq->where('questions.id', $question->id)))
            ->exists();
    }

    private function inExamWithHiddenAnswers(User $user, Question $question): bool
    {
        return Exam::query()
            ->published()
            ->whereHas('questions', fn ($q) => $q->where('questions.id', $question->id))
            ->whereHas('attempts', fn ($q) => $q->where('user_id', $user->id))
            ->where(fn ($q) => $q
                ->where('show_answers_after_submit', false)
                ->orWhere('available_to', '>', now()))
            ->exists();
    }

    private function hasAttempted(User $user, Question $question): bool
    {
        return QuestionAttempt::where('user_id', $user->id)->where('question_id', $question->id)->exists();
    }
}
