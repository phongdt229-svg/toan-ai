<?php

namespace App\Services\Teaching;

use App\Models\ExamAttempt;
use App\Models\QuestionAttempt;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\Learning\MasteryService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Chấm tay câu tự luận (§15: essay chờ người chấm).
 */
class ExamGradingService
{
    /**
     * Câu tự luận đạt từ mức này trở lên được tính là "đúng" khi tổng hợp mastery.
     * Tự luận không có đúng/sai tuyệt đối — cần một ngưỡng để thống kê theo chủ đề.
     */
    public const ESSAY_PASS_RATIO = 0.5;

    public function __construct(private readonly MasteryService $mastery) {}

    public function grade(StudentAnswer $answer, float $score, ?string $feedback, User $grader): ExamAttempt
    {
        $max = (float) $answer->max_score;

        if ($score < 0 || $score > $max) {
            throw new InvalidArgumentException("Điểm phải nằm trong khoảng 0 – {$max}.");
        }

        return DB::transaction(function () use ($answer, $score, $feedback, $grader, $max) {
            $isCorrect = $max > 0 && $score >= $max * self::ESSAY_PASS_RATIO;

            $answer->fill([
                'score' => $score,
                'is_correct' => $isCorrect,
                'feedback' => $feedback,
                'graded_by' => $grader->id,
                'graded_at' => now(),
            ])->save();

            /** @var ExamAttempt $attempt */
            $attempt = ExamAttempt::with('user')
                ->whereKey($answer->exam_attempt_id)
                ->lockForUpdate()
                ->firstOrFail();

            // Đồng bộ bản ghi lịch sử để mastery/AI thấy kết quả chấm tay.
            QuestionAttempt::query()
                ->where('context', QuestionAttempt::CONTEXT_EXAM)
                ->where('context_id', $attempt->id)
                ->where('question_id', $answer->question_id)
                ->update(['is_correct' => $isCorrect, 'score' => $score]);

            $this->recalculateAttempt($attempt);

            $topicId = DB::table('questions')->where('id', $answer->question_id)->value('topic_id');
            if ($topicId) {
                $this->mastery->recalculateForTopic($attempt->user, (int) $topicId);
            }

            return $attempt->refresh();
        });
    }

    /** Tổng điểm và trạng thái của lượt, tính lại từ các câu trả lời. */
    public function recalculateAttempt(ExamAttempt $attempt): void
    {
        $row = StudentAnswer::query()
            ->where('exam_attempt_id', $attempt->id)
            ->selectRaw('COALESCE(SUM(score), 0) as total')
            ->selectRaw('SUM(is_correct = 1) as correct')
            ->selectRaw('SUM(score IS NULL) as pending')
            ->first();

        $attempt->update([
            'score' => round((float) $row->total, 2),
            'correct_count' => (int) $row->correct,
            'status' => (int) $row->pending > 0 ? ExamAttempt::STATUS_SUBMITTED : ExamAttempt::STATUS_GRADED,
        ]);
    }
}
