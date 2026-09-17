<?php

namespace App\Services\Teaching;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\ExamAttempt;
use App\Models\Lesson;
use App\Models\StudentLessonProgress;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Cập nhật bảng tổng hợp `assignment_students` — nguồn cho mọi bộ lọc
 * "chưa làm / điểm thấp / tiến bộ" ở portal giáo viên.
 */
class AssignmentProgressService
{
    /**
     * Bài giao dạng đề: lấy lượt tốt nhất trong các lượt làm SAU khi bài được giao.
     * Lượt làm trước đó không tính — giáo viên giao đề là muốn học sinh làm lại bây giờ.
     */
    public function syncFromExamAttempt(ExamAttempt $attempt): void
    {
        $records = AssignmentStudent::query()
            ->where('student_id', $attempt->user_id)
            ->whereHas('assignment', fn ($q) => $q
                ->where('type', Assignment::TYPE_EXAM)
                ->where('exam_id', $attempt->exam_id))
            ->with('assignment')
            ->get();

        foreach ($records as $record) {
            $this->recalculateExamRecord($record);
        }
    }

    public function recalculateExamRecord(AssignmentStudent $record): void
    {
        $assignment = $record->assignment;

        $attempts = ExamAttempt::query()
            ->where('user_id', $record->student_id)
            ->where('exam_id', $assignment->exam_id)
            ->where('status', '!=', ExamAttempt::STATUS_IN_PROGRESS)
            ->where('started_at', '>=', $assignment->published_at)
            ->get();

        if ($attempts->isEmpty()) {
            return;
        }

        $best = $attempts->sortByDesc(fn ($a) => (float) $a->score)->first();
        $first = $attempts->sortBy('submitted_at')->first();

        $record->fill([
            // Còn tự luận chờ chấm thì chưa coi là xong.
            'status' => $best->status === ExamAttempt::STATUS_GRADED
                ? AssignmentStudent::STATUS_COMPLETED
                : AssignmentStudent::STATUS_SUBMITTED,
            'score' => $best->score,
            'max_score' => $best->total_points,
            'percent' => $best->percent(),
            'attempts_count' => $attempts->count(),
            'time_spent_seconds' => (int) $best->durationSeconds(),
            // Trễ hạn tính theo lần nộp ĐẦU TIÊN — làm lại sau hạn để nâng điểm không bị gắn cờ trễ.
            'is_late' => $this->isLate($assignment, $first->submitted_at),
            'completed_at' => $record->completed_at ?? $first->submitted_at,
        ])->save();
    }

    public function syncFromLesson(User $student, Lesson $lesson): void
    {
        $completedAt = StudentLessonProgress::query()
            ->where('user_id', $student->id)
            ->where('lesson_id', $lesson->id)
            ->value('completed_at');

        if (! $completedAt) {
            return;
        }

        AssignmentStudent::query()
            ->where('student_id', $student->id)
            ->where('status', AssignmentStudent::STATUS_ASSIGNED)
            ->whereHas('assignment', fn ($q) => $q
                ->where('type', Assignment::TYPE_LESSON)
                ->where('lesson_id', $lesson->id))
            ->with('assignment')
            ->get()
            ->each(fn (AssignmentStudent $record) => $this->markLessonDone($record, $completedAt));
    }

    /**
     * Bài học đã hoàn thành trước khi được giao vẫn tính là xong (không trễ):
     * đọc lại lý thuyết đã nắm không có ý nghĩa, khác với làm lại đề.
     */
    public function markLessonDone(AssignmentStudent $record, \DateTimeInterface|string $completedAt): void
    {
        $completedAt = $completedAt instanceof \DateTimeInterface ? $completedAt : Carbon::parse($completedAt);
        $assignment = $record->assignment;

        $record->fill([
            'status' => AssignmentStudent::STATUS_COMPLETED,
            'attempts_count' => 1,
            'is_late' => $completedAt > $assignment->published_at && $this->isLate($assignment, $completedAt),
            'completed_at' => $completedAt,
        ])->save();
    }

    public function isLate(Assignment $assignment, ?\DateTimeInterface $at): bool
    {
        return $assignment->due_at !== null && $at !== null && $at > $assignment->due_at;
    }
}
