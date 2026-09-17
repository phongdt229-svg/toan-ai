<?php

namespace App\Services\Teaching;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\StudentLessonProgress;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Giao bài (§17): Chọn lớp → Chọn học sinh → Chọn bài → Deadline → Cho phép làm lại → Giao bài.
 */
class AssignmentService
{
    public function __construct(private readonly AssignmentProgressService $progress) {}

    /**
     * @param  array{
     *     title: string, description?: ?string, type: string,
     *     exam_id?: ?int, lesson_id?: ?int, question_ids?: array<int, int>,
     *     assign_to_all: bool, student_ids?: array<int, int>,
     *     due_at?: ?string, allow_retry: bool, max_attempts?: ?int
     * }  $data
     */
    public function create(SchoolClass $class, User $teacher, array $data): Assignment
    {
        $recipients = $this->resolveRecipients($class, $data);

        return DB::transaction(function () use ($class, $teacher, $data, $recipients) {
            $assignment = Assignment::create([
                'class_id' => $class->id,
                'teacher_id' => $teacher->id,
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'type' => $data['type'],
                'exam_id' => $data['type'] === Assignment::TYPE_EXAM ? $this->publishedExam($data)->id : null,
                'lesson_id' => $data['type'] === Assignment::TYPE_LESSON ? $this->publishedLesson($data)->id : null,
                'assign_to_all' => $data['assign_to_all'],
                'due_at' => $data['due_at'] ?? null,
                // Bài dạng đề dùng số lượt của chính đề — hai giới hạn chồng nhau chỉ gây rối.
                'allow_retry' => $data['type'] === Assignment::TYPE_QUESTION_SET && $data['allow_retry'],
                'max_attempts' => $data['type'] === Assignment::TYPE_QUESTION_SET && $data['allow_retry']
                    ? max(2, (int) ($data['max_attempts'] ?? 2))
                    : 1,
                'status' => Assignment::STATUS_PUBLISHED,
                'published_at' => now(),
            ]);

            if ($assignment->type === Assignment::TYPE_QUESTION_SET) {
                $this->attachQuestions($assignment, $teacher, $data['question_ids'] ?? []);
            }

            foreach ($recipients as $studentId) {
                $this->deliver($assignment, $studentId);
            }

            return $assignment;
        });
    }

    /** Học sinh mới vào lớp nhận các bài "cả lớp" còn chưa đóng. */
    public function deliverOpenClassAssignments(SchoolClass $class, User $student): void
    {
        Assignment::query()
            ->where('class_id', $class->id)
            ->where('assign_to_all', true)
            ->where('status', Assignment::STATUS_PUBLISHED)
            ->get()
            ->each(fn (Assignment $a) => $this->deliver($a, $student->id));
    }

    /** @param  array<string, mixed>  $data */
    public function update(Assignment $assignment, array $data): Assignment
    {
        return DB::transaction(function () use ($assignment, $data) {
            $assignment->update([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'allow_retry' => $assignment->type === Assignment::TYPE_QUESTION_SET && $data['allow_retry'],
                'max_attempts' => $assignment->type === Assignment::TYPE_QUESTION_SET && $data['allow_retry']
                    ? max(2, (int) ($data['max_attempts'] ?? 2))
                    : 1,
            ]);

            // Dời hạn nộp → cờ "trễ hạn" của bài đã nộp phải tính lại theo hạn mới.
            $assignment->recipients()->whereNotNull('completed_at')->get()
                ->each(fn (AssignmentStudent $r) => $r->update([
                    'is_late' => $this->progress->isLate($assignment, $r->completed_at),
                ]));

            return $assignment;
        });
    }

    public function toggleClosed(Assignment $assignment): Assignment
    {
        $assignment->update([
            'status' => $assignment->isClosed() ? Assignment::STATUS_PUBLISHED : Assignment::STATUS_CLOSED,
        ]);

        return $assignment;
    }

    private function deliver(Assignment $assignment, int $studentId): void
    {
        $record = AssignmentStudent::firstOrCreate(
            ['assignment_id' => $assignment->id, 'student_id' => $studentId],
            ['status' => AssignmentStudent::STATUS_ASSIGNED],
        );

        if (! $record->wasRecentlyCreated) {
            return;
        }

        if ($assignment->type === Assignment::TYPE_LESSON) {
            $completedAt = StudentLessonProgress::query()
                ->where('user_id', $studentId)
                ->where('lesson_id', $assignment->lesson_id)
                ->value('completed_at');

            if ($completedAt) {
                $record->setRelation('assignment', $assignment);
                $this->progress->markLessonDone($record, $completedAt);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, int>
     */
    private function resolveRecipients(SchoolClass $class, array $data): Collection
    {
        $active = $class->activeStudents()->pluck('users.id');

        $ids = $data['assign_to_all']
            ? $active
            : collect($data['student_ids'] ?? [])->map(fn ($v) => (int) $v)->intersect($active)->values();

        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'student_ids' => $data['assign_to_all']
                    ? 'Lớp chưa có học sinh nào.'
                    : 'Phải chọn ít nhất một học sinh đang trong lớp.',
            ]);
        }

        return $ids;
    }

    /** @param  array<int, int>  $questionIds */
    private function attachQuestions(Assignment $assignment, User $teacher, array $questionIds): void
    {
        $questions = Question::query()
            ->whereIn('id', $questionIds)
            ->where(fn ($q) => $q->where('status', 'published')->orWhere('created_by', $teacher->id))
            // Bài tập về nhà chấm tự động; câu tự luận hãy giao qua đề kiểm tra (có chấm tay).
            ->where('type', '!=', Question::TYPE_ESSAY)
            ->get();

        if ($questions->isEmpty()) {
            throw ValidationException::withMessages([
                'question_ids' => 'Phải chọn ít nhất một câu hỏi tự chấm được (không gồm tự luận).',
            ]);
        }

        $order = array_flip(array_values($questionIds));

        foreach ($questions->sortBy(fn ($q) => $order[$q->id] ?? PHP_INT_MAX)->values() as $i => $question) {
            $assignment->questions()->attach($question->id, [
                'sort_order' => $i + 1,
                'points' => $question->points,
            ]);
        }
    }

    /** @param  array<string, mixed>  $data */
    private function publishedExam(array $data): Exam
    {
        $exam = Exam::published()->find($data['exam_id'] ?? null);

        if (! $exam) {
            throw ValidationException::withMessages(['exam_id' => 'Phải chọn một đề kiểm tra đã xuất bản.']);
        }

        return $exam;
    }

    /** @param  array<string, mixed>  $data */
    private function publishedLesson(array $data): Lesson
    {
        $lesson = Lesson::published()->find($data['lesson_id'] ?? null);

        if (! $lesson) {
            throw ValidationException::withMessages(['lesson_id' => 'Phải chọn một bài học đã xuất bản.']);
        }

        return $lesson;
    }
}
