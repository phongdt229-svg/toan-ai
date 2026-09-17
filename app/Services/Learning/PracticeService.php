<?php

namespace App\Services\Learning;

use App\Models\Package;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\User;
use App\Services\FeatureLockedException;
use App\Services\SubscriptionService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class PracticeService
{
    public function __construct(
        private readonly GradingService $grading,
        private readonly MasteryService $mastery,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /**
     * §18 Free: "một số bài tập" — giới hạn số câu luyện tập mỗi ngày theo package_features.
     * Trả số câu còn được làm hôm nay (null = không giới hạn) để bộ đề không vượt quá phần còn lại.
     *
     * @throws FeatureLockedException
     */
    public function ensureDailyLimit(User $user): ?int
    {
        ['limit' => $limit, 'remaining' => $remaining] = $this->dailyStatus($user);

        if ($remaining === 0) {
            throw new FeatureLockedException(
                "Hôm nay em đã luyện đủ {$limit} câu của gói miễn phí. Mai luyện tiếp, hoặc nâng cấp để luyện không giới hạn.",
                // Gói trả phí rẻ nhất — mọi gói trả phí đều bỏ giới hạn luyện tập.
                Package::active()->where('price', '>', 0)->orderBy('price')->first(),
            );
        }

        return $remaining;
    }

    /** @return array{used: int, limit: ?int, remaining: ?int} */
    public function dailyStatus(User $user): array
    {
        $limit = $this->subscriptions->limit($user, 'practice.daily_questions');
        $limit = $limit === false ? null : $limit;
        $used = $this->usedToday($user);

        return ['used' => $used, 'limit' => $limit, 'remaining' => $limit === null ? null : max(0, $limit - $used)];
    }

    public function usedToday(User $user): int
    {
        return QuestionAttempt::query()
            ->where('user_id', $user->id)
            ->where('context', QuestionAttempt::CONTEXT_PRACTICE)
            ->where('created_at', '>=', today())
            ->count();
    }

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
