<?php

namespace App\Services\Learning;

use App\Events\MasteryUpdated;
use App\Models\QuestionAttempt;
use App\Models\StudentTopicMastery;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tính mức nắm vững từng chủ đề (§9, §11).
 *
 * Trọng số theo độ khó: làm đúng câu khó đáng giá hơn câu dễ, và làm sai câu dễ
 * bị phạt nặng hơn — nếu không, học sinh chỉ làm câu dễ cũng đạt 100%.
 */
class MasteryService
{
    public const WEIGHTS = ['easy' => 1.0, 'medium' => 1.5, 'hard' => 2.0];

    /** Cần tối thiểu số lần làm mới coi điểm là đáng tin. */
    public const MIN_ATTEMPTS_FOR_CONFIDENCE = 5;

    public function recalculateForTopic(User $user, int $topicId): StudentTopicMastery
    {
        $attempts = QuestionAttempt::query()
            ->where('user_id', $user->id)
            ->where('topic_id', $topicId)
            ->whereNotNull('is_correct')   // bỏ qua câu đang chờ chấm
            ->get(['is_correct', 'difficulty', 'time_spent_seconds']);

        $correct = $attempts->where('is_correct', true)->count();
        $wrong = $attempts->where('is_correct', false)->count();

        $earned = 0.0;
        $possible = 0.0;

        foreach ($attempts as $attempt) {
            $weight = self::WEIGHTS[$attempt->difficulty] ?? 1.0;
            $possible += $weight;

            if ($attempt->is_correct) {
                $earned += $weight;
            }
        }

        $score = $possible > 0 ? (int) round($earned / $possible * 100) : 0;
        $avgTime = $attempts->isNotEmpty() ? (int) round($attempts->avg('time_spent_seconds')) : 0;

        return StudentTopicMastery::updateOrCreate(
            ['user_id' => $user->id, 'topic_id' => $topicId],
            [
                'correct_count' => $correct,
                'wrong_count' => $wrong,
                'avg_time_seconds' => $avgTime,
                'mastery_score' => $score,
                'last_practiced_at' => now(),
            ],
        );
    }

    /** @param  array<int, int>  $topicIds */
    public function recalculateForTopics(User $user, array $topicIds): void
    {
        DB::transaction(function () use ($user, $topicIds) {
            foreach (array_unique(array_filter($topicIds)) as $topicId) {
                $this->recalculateForTopic($user, $topicId);
            }
        });

        MasteryUpdated::dispatch($user);
    }

    /**
     * Chủ đề yếu — nguồn cho "AI đề xuất ôn lại" (§14) và lộ trình (§35).
     *
     * @return Collection<int, StudentTopicMastery>
     */
    public function weakTopics(User $user, int $limit = 5)
    {
        return StudentTopicMastery::query()
            ->where('user_id', $user->id)
            ->where('mastery_score', '<', StudentTopicMastery::WEAK_THRESHOLD)
            // Chỉ tính chủ đề đã làm đủ nhiều, tránh báo "yếu" sau 1 câu sai.
            ->whereRaw('(correct_count + wrong_count) >= ?', [self::MIN_ATTEMPTS_FOR_CONFIDENCE])
            ->with('topic')
            ->orderBy('mastery_score')
            ->limit($limit)
            ->get();
    }

    /** @return Collection<int, StudentTopicMastery> */
    public function strongTopics(User $user, int $limit = 5)
    {
        return StudentTopicMastery::query()
            ->where('user_id', $user->id)
            ->where('mastery_score', '>=', 80)
            ->whereRaw('(correct_count + wrong_count) >= ?', [self::MIN_ATTEMPTS_FOR_CONFIDENCE])
            ->with('topic')
            ->orderByDesc('mastery_score')
            ->limit($limit)
            ->get();
    }
}
