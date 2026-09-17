<?php

namespace App\Services\Learning;

use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PracticeService
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly MasteryService $mastery,
    ) {}

    /**
     * Bốc ngẫu nhiên một bộ câu hỏi đã xuất bản.
     *
     * @return Collection<int, Question>
     */
    public function buildSet(int $topicId, ?string $difficulty, int $limit): Collection
    {
        return Question::query()
            ->published()
            ->where('topic_id', $topicId)
            // Tự luận cần người chấm nên không đưa vào luyện tập tự động.
            ->where('type', '!=', Question::TYPE_ESSAY)
            ->when($difficulty, fn ($q) => $q->where('difficulty', $difficulty))
            ->inRandomOrder()
            ->limit($limit)
            ->with('options')
            ->get();
    }

    /**
     * Chấm bài luyện tập, ghi lịch sử từng câu và cập nhật mastery.
     *
     * @param  array<int, int>  $questionIds  bộ câu hỏi đã phát ra (server giữ, không tin client)
     * @param  array<int, mixed>  $answers  question_id => answer
     * @param  array<int, int>  $timeSpent  question_id => giây
     * @return array{questions: Collection<int, Question>, results: array<int, GradingResult>, score: float, max_score: float, correct: int, pending: int, percent: int}
     */
    public function submit(User $user, array $questionIds, array $answers, array $timeSpent = []): array
    {
        $questions = Question::query()
            ->whereIn('id', $questionIds)
            ->with('options')
            ->get();

        $graded = $this->grading->gradeMany($questions, $answers);

        DB::transaction(function () use ($user, $questions, $graded, $answers, $timeSpent) {
            foreach ($questions as $question) {
                /** @var GradingResult $result */
                $result = $graded['results'][$question->id];

                $attemptNo = QuestionAttempt::where('user_id', $user->id)
                    ->where('question_id', $question->id)
                    ->max('attempt_no') ?? 0;

                QuestionAttempt::create([
                    'user_id' => $user->id,
                    'question_id' => $question->id,
                    'topic_id' => $question->topic_id,
                    'context' => QuestionAttempt::CONTEXT_PRACTICE,
                    'difficulty' => $question->difficulty,
                    'answer' => ['value' => $answers[$question->id] ?? null],
                    'is_correct' => $result->isCorrect,
                    'score' => $result->score,
                    // Trần 30 phút/câu để một tab bỏ quên không làm hỏng thống kê.
                    'time_spent_seconds' => min(1800, max(0, (int) ($timeSpent[$question->id] ?? 0))),
                    'attempt_no' => $attemptNo + 1,
                ]);
            }

            $this->mastery->recalculateForTopics($user, $questions->pluck('topic_id')->all());
        });

        $percent = $graded['max_score'] > 0
            ? (int) round($graded['score'] / $graded['max_score'] * 100)
            : 0;

        return [...$graded, 'questions' => $questions, 'percent' => $percent];
    }
}
