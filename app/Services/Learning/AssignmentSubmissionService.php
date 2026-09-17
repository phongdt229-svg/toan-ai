<?php

namespace App\Services\Learning;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\AssignmentSubmission;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;
use App\Services\Teaching\AssignmentProgressService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Học sinh làm bài giao dạng bộ câu hỏi (bài tập về nhà — không bấm giờ, có hạn nộp).
 */
class AssignmentSubmissionService
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly MasteryService $mastery,
        private readonly AssignmentProgressService $progress,
    ) {}

    /** @return Collection<int, Question> */
    public function questionsFor(Assignment $assignment): Collection
    {
        return $assignment->questions()->with('options')->get();
    }

    /** Lý do không được nộp, hoặc null nếu được. */
    public function blockReason(Assignment $assignment, ?AssignmentStudent $record): ?string
    {
        return match (true) {
            $record === null => 'Bạn không được giao bài này.',
            $assignment->isClosed() => 'Giáo viên đã đóng bài này.',
            $record->attempts_count >= $assignment->effectiveMaxAttempts() => $assignment->allow_retry
                ? "Bạn đã dùng hết {$assignment->effectiveMaxAttempts()} lượt nộp."
                : 'Bạn đã nộp bài này.',
            default => null,
        };
    }

    /**
     * @param  array<int, mixed>  $answers  question_id => giá trị trả lời
     * @param  array<int, int>  $timeSpent  question_id => giây
     */
    public function submit(Assignment $assignment, User $student, array $answers, array $timeSpent = []): AssignmentSubmission
    {
        return DB::transaction(function () use ($assignment, $student, $answers, $timeSpent) {
            /** @var AssignmentStudent|null $record */
            $record = AssignmentStudent::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $student->id)
                ->lockForUpdate()
                ->first();

            if ($reason = $this->blockReason($assignment, $record)) {
                throw new RuntimeException($reason);
            }

            $questions = $this->questionsFor($assignment);
            $now = now();
            $isLate = $this->progress->isLate($assignment, $now);

            $score = 0.0;
            $max = 0.0;
            $correct = 0;
            $stored = [];
            $questionAttempts = [];
            $totalSeconds = 0;

            foreach ($questions as $question) {
                $value = $answers[$question->id] ?? null;
                $points = (float) $question->pivot->points;
                $result = $this->grading->grade($question, $value, $points);

                $score += $result->score;
                $max += $points;
                $correct += $result->isCorrect ? 1 : 0;

                $stored[$question->id] = [
                    'value' => $value,
                    'is_correct' => $result->isCorrect,
                    'score' => $result->score,
                ];

                $seconds = min(1800, max(0, (int) ($timeSpent[$question->id] ?? 0)));
                $totalSeconds += $seconds;

                $questionAttempts[] = [
                    'question' => $question,
                    'value' => $value,
                    'result' => $result,
                    'seconds' => $seconds,
                ];
            }

            $submission = AssignmentSubmission::create([
                'assignment_id' => $assignment->id,
                'student_id' => $student->id,
                'attempt_no' => $record->attempts_count + 1,
                'answers' => $stored,
                'score' => round($score, 2),
                'max_score' => round($max, 2),
                'correct_count' => $correct,
                'time_spent_seconds' => $totalSeconds,
                'is_late' => $isLate,
                'submitted_at' => $now,
            ]);

            foreach ($questionAttempts as $row) {
                QuestionAttempt::create([
                    'user_id' => $student->id,
                    'question_id' => $row['question']->id,
                    'topic_id' => $row['question']->topic_id,
                    'context' => QuestionAttempt::CONTEXT_ASSIGNMENT,
                    'context_id' => $submission->id,
                    'difficulty' => $row['question']->difficulty,
                    'answer' => ['value' => $row['value']],
                    'is_correct' => $row['result']->isCorrect,
                    'score' => $row['result']->score,
                    'time_spent_seconds' => $row['seconds'],
                    'attempt_no' => $submission->attempt_no,
                ]);
            }

            $percent = $max > 0 ? (int) round($score / $max * 100) : 0;
            $isFirst = $record->attempts_count === 0;

            $record->fill([
                'status' => AssignmentStudent::STATUS_COMPLETED,
                'attempts_count' => $record->attempts_count + 1,
                'time_spent_seconds' => $record->time_spent_seconds + $totalSeconds,
            ]);

            // Giữ điểm cao nhất; làm lại để cải thiện, không bị kéo điểm xuống.
            if ($record->percent === null || $percent > $record->percent) {
                $record->fill(['score' => round($score, 2), 'max_score' => round($max, 2), 'percent' => $percent]);
            }

            // Trễ hạn tính theo lần nộp đầu tiên.
            if ($isFirst) {
                $record->fill(['is_late' => $isLate, 'completed_at' => $now]);
            }

            $record->save();

            $this->mastery->recalculateForTopics($student, $questions->pluck('topic_id')->filter()->all());

            return $submission;
        });
    }
}
