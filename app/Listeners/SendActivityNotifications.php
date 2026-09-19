<?php

namespace App\Listeners;

use App\Events\ExamAttemptFinished;
use App\Events\LessonCompleted;
use App\Models\Assignment;
use App\Models\ExamAttempt;
use App\Models\User;
use App\Notifications\AssignmentSubmittedByStudent;
use App\Notifications\ChildScoreLow;
use App\Notifications\ExamResultReady;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Thông báo trong app khi học sinh làm đề / học xong bài (§10, §14) — tách khỏi
 * SyncAssignmentProgress vì đây là việc khác (báo người dùng), không phải tính lại tiến độ.
 *
 * Ngưỡng điểm thấp báo phụ huynh: dưới 50% — không cấu hình được qua UI, chỉ là một
 * ngưỡng hợp lý ban đầu.
 */
class SendActivityNotifications implements ShouldHandleEventsAfterCommit
{
    private const LOW_SCORE_PERCENT = 50;

    public function handleExamAttemptFinished(ExamAttemptFinished $event): void
    {
        $attempt = $event->attempt;

        // Còn tự luận chờ chấm tay — chưa có điểm cuối cùng để báo (§16).
        if ($attempt->status !== ExamAttempt::STATUS_GRADED) {
            return;
        }

        $attempt->loadMissing('exam', 'user');
        $student = $attempt->user;
        $percent = $attempt->percent();

        $student->notify(new ExamResultReady($attempt));

        if ($percent !== null && $percent < self::LOW_SCORE_PERCENT) {
            foreach ($student->linkedParents()->get() as $parent) {
                $parent->notify(new ChildScoreLow($student, $attempt->exam->title, $percent));
            }
        }

        $this->notifyTeachersOfSubmission($student, Assignment::TYPE_EXAM, $attempt->exam_id);
    }

    public function handleLessonCompleted(LessonCompleted $event): void
    {
        $this->notifyTeachersOfSubmission($event->student, Assignment::TYPE_LESSON, $event->lesson->id);
    }

    /** Bài giao (đề hoặc bài học) khớp với việc học sinh vừa làm — báo giáo viên đã giao. */
    private function notifyTeachersOfSubmission(User $student, string $type, int $refId): void
    {
        $column = $type === Assignment::TYPE_EXAM ? 'exam_id' : 'lesson_id';

        Assignment::query()
            ->where('type', $type)
            ->where($column, $refId)
            ->whereHas('recipients', fn ($q) => $q->where('student_id', $student->id))
            ->with('teacher')
            ->get()
            ->each(fn (Assignment $assignment) => $assignment->teacher?->notify(
                new AssignmentSubmittedByStudent($assignment, $student),
            ));
    }
}
