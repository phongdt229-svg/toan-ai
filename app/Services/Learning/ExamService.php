<?php

namespace App\Services\Learning;

use App\Events\ExamAttemptFinished;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\StudentAnswer;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Luồng làm đề (§16): Start → Answer → Submit → Calculate result → Review.
 *
 * Nguyên tắc chống gian lận:
 * - Thời gian do server quyết (`expires_at`), client chỉ hiển thị.
 * - Thứ tự câu/lựa chọn xáo một lần lúc bắt đầu và lưu lại.
 * - Mỗi học sinh chỉ có một lượt đang làm cho mỗi đề.
 * - Đáp án chỉ được chấm từ DB, không tin gì client gửi ngoài giá trị trả lời.
 */
class ExamService
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly MasteryService $mastery,
        private readonly AccessControlService $access,
    ) {}

    /**
     * Bắt đầu lượt mới, hoặc trả lại lượt đang làm dở.
     *
     * @throws ExamException
     */
    public function start(User $user, Exam $exam): ExamAttempt
    {
        if (! $exam->isPublished() || ! $exam->isOpen() || ! $this->access->canAccessLevel($user, $exam->access_level)) {
            throw ExamException::notAvailable();
        }

        return DB::transaction(function () use ($user, $exam) {
            // Khoá theo user để hai tab bấm "Bắt đầu" cùng lúc không tạo ra hai lượt.
            User::whereKey($user->id)->lockForUpdate()->first();

            $current = ExamAttempt::query()
                ->where('user_id', $user->id)
                ->where('exam_id', $exam->id)
                ->where('status', ExamAttempt::STATUS_IN_PROGRESS)
                ->first();

            if ($current) {
                if (! $current->isPastDeadline()) {
                    return $current;
                }

                // Lượt cũ đã quá giờ mà chưa ai chốt → chốt luôn rồi tính như lượt đã dùng.
                $this->submit($current, auto: true);
            }

            $used = ExamAttempt::where('user_id', $user->id)->where('exam_id', $exam->id)->count();

            if ($used >= $exam->max_attempts) {
                throw ExamException::noAttemptsLeft($exam->max_attempts);
            }

            $questions = $exam->questions()->with('options')->get();

            if ($questions->isEmpty()) {
                throw ExamException::empty();
            }

            $now = now();

            return ExamAttempt::create([
                'exam_id' => $exam->id,
                'user_id' => $user->id,
                'attempt_no' => $used + 1,
                'started_at' => $now,
                'expires_at' => $now->copy()->addMinutes($exam->duration_minutes),
                'status' => ExamAttempt::STATUS_IN_PROGRESS,
                'total_points' => $questions->sum(fn ($q) => (float) $q->pivot->points),
                'question_order' => $this->orderQuestions($exam, $questions),
                'option_order' => $this->orderOptions($exam, $questions),
            ]);
        });
    }

    /**
     * Lưu nháp một câu (autosave). Không chấm ở bước này.
     *
     * @throws ExamException
     */
    public function saveAnswer(ExamAttempt $attempt, int $questionId, mixed $value, int $timeSpent = 0): StudentAnswer
    {
        $attempt->loadMissing('exam');

        if (! $attempt->isInProgress()) {
            throw ExamException::alreadySubmitted();
        }

        if ($attempt->isPastDeadline()) {
            $this->submit($attempt, auto: true);

            throw ExamException::timeUp();
        }

        if (! in_array($questionId, $attempt->question_order, true)) {
            throw ExamException::notInAttempt();
        }

        $answer = StudentAnswer::firstOrNew([
            'exam_attempt_id' => $attempt->id,
            'question_id' => $questionId,
        ]);

        $answer->answer = ['value' => $value];
        // Client gửi tổng số giây đã ở câu này; không cho vượt thời lượng cả bài.
        $cap = $attempt->exam->duration_minutes * 60;
        $answer->time_spent_seconds = min($cap, max((int) $answer->time_spent_seconds, $timeSpent));
        $answer->save();

        return $answer;
    }

    /**
     * Nộp bài và chấm. Gọi lại trên lượt đã nộp thì không làm gì (bấm nộp hai lần vô hại).
     */
    public function submit(ExamAttempt $attempt, bool $auto = false): ExamAttempt
    {
        return DB::transaction(function () use ($attempt, $auto) {
            /** @var ExamAttempt $locked */
            $locked = ExamAttempt::with('exam', 'user')->whereKey($attempt->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isInProgress()) {
                return $locked;
            }

            $questions = $locked->exam->questions()->with('options')->get()->keyBy('id');
            $saved = $locked->answers()->get()->keyBy('question_id');

            $score = 0.0;
            $correct = 0;
            $pending = 0;
            $topicIds = [];

            foreach ($locked->question_order as $questionId) {
                /** @var Question|null $question */
                $question = $questions->get($questionId);

                if (! $question) {
                    continue; // câu đã bị gỡ khỏi đề sau khi lượt bắt đầu
                }

                $row = $saved->get($questionId) ?? new StudentAnswer([
                    'exam_attempt_id' => $locked->id,
                    'question_id' => $questionId,
                ]);

                $value = $row->answer['value'] ?? null;
                $points = (float) $question->pivot->points;
                $result = $this->grading->grade($question, $value, $points);

                $row->is_correct = $result->isCorrect;
                $row->score = $result->needsManualGrading ? null : $result->score;
                $row->max_score = $points;
                $row->save();

                $score += $result->score;
                $correct += $result->isCorrect === true ? 1 : 0;
                $pending += $result->needsManualGrading ? 1 : 0;

                QuestionAttempt::create([
                    'user_id' => $locked->user_id,
                    'question_id' => $questionId,
                    'topic_id' => $question->topic_id,
                    'context' => QuestionAttempt::CONTEXT_EXAM,
                    'context_id' => $locked->id,
                    'difficulty' => $question->difficulty,
                    'answer' => ['value' => $value],
                    'is_correct' => $result->isCorrect,
                    'score' => $result->score,
                    'time_spent_seconds' => (int) $row->time_spent_seconds,
                    'attempt_no' => $locked->attempt_no,
                ]);

                $topicIds[] = $question->topic_id;
            }

            $locked->fill([
                'status' => $pending > 0 ? ExamAttempt::STATUS_SUBMITTED : ExamAttempt::STATUS_GRADED,
                'score' => round($score, 2),
                'correct_count' => $correct,
                // Tự nộp do hết giờ → thời điểm nộp là lúc hết giờ, không phải lúc cron chạy.
                'submitted_at' => $auto ? $locked->expires_at : now(),
                'auto_submitted' => $auto,
            ])->save();

            $this->mastery->recalculateForTopics($locked->user, array_filter($topicIds));

            ExamAttemptFinished::dispatch($locked);

            return $locked;
        });
    }

    /** Chốt mọi lượt đã quá giờ mà học sinh không nộp (tab đóng, mất mạng…). */
    public function finalizeExpired(): int
    {
        $count = 0;

        ExamAttempt::query()
            ->where('status', ExamAttempt::STATUS_IN_PROGRESS)
            ->where('expires_at', '<', now()->subSeconds(ExamAttempt::GRACE_SECONDS))
            ->with('exam', 'user')
            ->chunkById(100, function ($attempts) use (&$count) {
                foreach ($attempts as $attempt) {
                    $this->submit($attempt, auto: true);
                    $count++;
                }
            });

        return $count;
    }

    /**
     * Câu hỏi theo đúng thứ tự của lượt làm, lựa chọn cũng theo thứ tự đã xáo.
     *
     * @return Collection<int, Question>
     */
    public function questionsForAttempt(ExamAttempt $attempt): Collection
    {
        $attempt->loadMissing('exam');
        $questions = $attempt->exam->questions()->with('options')->get()->keyBy('id');
        $optionOrder = $attempt->option_order ?? [];

        return collect($attempt->question_order)
            ->map(fn ($id) => $questions->get($id))
            ->filter()
            ->each(function (Question $question) use ($optionOrder) {
                $order = $optionOrder[$question->id] ?? null;

                if ($order) {
                    $question->setRelation(
                        'options',
                        $question->options->sortBy(fn ($o) => array_search($o->id, $order, true))->values(),
                    );
                }
            })
            ->values();
    }

    /** @return array<int, int> */
    private function orderQuestions(Exam $exam, Collection $questions): array
    {
        $ids = $questions->pluck('id');

        return ($exam->shuffle_questions ? $ids->shuffle() : $ids)->values()->all();
    }

    /** @return array<int, array<int, int>> */
    private function orderOptions(Exam $exam, Collection $questions): array
    {
        if (! $exam->shuffle_options) {
            return [];
        }

        return $questions
            ->filter(fn (Question $q) => $q->usesOptions())
            ->mapWithKeys(fn (Question $q) => [$q->id => $q->options->pluck('id')->shuffle()->values()->all()])
            ->all();
    }

    /**
     * Tỉ lệ đúng theo chủ đề trong một lượt — số liệu cho biểu đồ ở trang kết quả.
     *
     * @return array<int, array{topic: string, percent: int}>
     */
    public function breakdownByTopic(ExamAttempt $attempt): array
    {
        return StudentAnswer::query()
            ->where('exam_attempt_id', $attempt->id)
            ->whereNotNull('score')
            ->join('questions', 'questions.id', '=', 'student_answers.question_id')
            ->leftJoin('topics', 'topics.id', '=', 'questions.topic_id')
            ->groupBy('topics.id', 'topics.name')
            ->selectRaw("COALESCE(topics.name, 'Khác') as topic")
            ->selectRaw('ROUND(SUM(student_answers.score) / NULLIF(SUM(student_answers.max_score), 0) * 100) as percent')
            ->get()
            ->map(fn ($r) => ['topic' => $r->topic, 'percent' => (int) $r->percent])
            ->all();
    }
}
