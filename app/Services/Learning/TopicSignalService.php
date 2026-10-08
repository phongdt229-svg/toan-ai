<?php

namespace App\Services\Learning;

use App\Models\AiConversation;
use App\Models\QuestionAttempt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Tín hiệu học tập theo chủ đề (TA-04) + thói quen nhờ AI (TA-18), đặc tả "Logic §5":
 * không chỉ nhìn điểm số mà cả số lần xin gợi ý, sai rồi sửa đúng, thời gian làm, lỗi lặp lại, độ ổn định.
 *
 * Mọi thứ là quy tắc + trọng số cố định (không gọi LLM) để luôn trả lời được "vì sao gợi ý chủ đề này":
 * mỗi chủ đề có `need` 0–100 và danh sách `reasons` bằng tiếng Việt.
 */
class TopicSignalService
{
    public const WINDOW_DAYS = 14;

    /** Trọng số "cần ôn" — tổng dương = 100, "sai rồi tự sửa đúng" là tín hiệu tốt nên trừ điểm. */
    public const WEIGHTS = [
        'wrong_rate' => 35,
        'repeated_errors' => 20,
        'hints' => 15,
        'explains' => 10,
        'slow' => 10,
        'unstable' => 10,
        'self_fixed' => -10,
    ];

    /** Ít hơn số lượt này thì chưa kết luận gì về chủ đề. */
    public const MIN_ATTEMPTS = 3;

    /**
     * @return Collection<int, array{topic_id: int, attempts: int, wrong_rate: float, repeated_errors: int, self_fixed: int,
     *     hints: int, explains: int, slow: bool, unstable: bool, need: int, reasons: array<int, string>}> keyBy topic_id, `need` giảm dần
     */
    public function forStudent(User $student): Collection
    {
        $since = now()->subDays(self::WINDOW_DAYS);

        $attempts = QuestionAttempt::query()
            ->where('user_id', $student->id)
            ->whereNotNull('topic_id')
            ->where('created_at', '>=', $since)
            ->where('context', '!=', QuestionAttempt::CONTEXT_PLACEMENT)
            ->orderBy('created_at')
            ->get(['topic_id', 'question_id', 'is_correct', 'time_spent_seconds', 'created_at']);

        $help = $this->aiHelp($student, $since);
        $overallAvgTime = $attempts->where('time_spent_seconds', '>', 0)->avg('time_spent_seconds');

        return $attempts->groupBy('topic_id')
            ->filter(fn ($rows) => $rows->count() >= self::MIN_ATTEMPTS)
            ->map(fn ($rows, $topicId) => $this->signalsFor((int) $topicId, $rows, $help->get($topicId, collect()), $overallAvgTime))
            ->sortByDesc('need');
    }

    /**
     * TA-18: em đang tự học hay chủ yếu xin lời giải? Gộp từ mọi chủ đề trong 14 ngày.
     *
     * @return array{hints: int, explains: int, self_solved_after_hint: int, explain_share: ?int, label: string, nudge: ?string}
     */
    public function helpSeeking(User $student): array
    {
        $since = now()->subDays(self::WINDOW_DAYS);
        $help = $this->aiHelp($student, $since)->flatten(1);

        $hints = $help->where('mode', 'hint');
        $explains = $help->where('mode', 'explain')->count();

        // Gợi ý có tác dụng = sau gợi ý, em tự làm đúng chính câu đó.
        $selfSolved = $hints->filter(fn ($h) => QuestionAttempt::query()
            ->where('user_id', $student->id)
            ->where('question_id', $h['question_id'])
            ->where('is_correct', true)
            ->where('created_at', '>', $h['at'])
            ->exists())->count();

        $total = $hints->count() + $explains;
        $share = $total > 0 ? (int) round($explains / $total * 100) : null;

        [$label, $nudge] = match (true) {
            $total < 3 => ['Chưa đủ dữ liệu', null],
            $share >= 60 && $explains >= 5 => ['Hay xin lời giải',
                'Em hay xem lời giải đầy đủ. Thử bấm "Gợi ý" trước và tự làm tiếp — tự nghĩ ra mới nhớ lâu em ạ.'],
            $hints->count() > 0 && $selfSolved / $hints->count() >= 0.5 => ['Tự học tốt', null],
            default => ['Bình thường', null],
        };

        return [
            'hints' => $hints->count(),
            'explains' => $explains,
            'self_solved_after_hint' => $selfSolved,
            'explain_share' => $share,
            'label' => $label,
            'nudge' => $nudge,
        ];
    }

    /** @param  Collection<int, array{mode: string, question_id: int, at: mixed}>  $help */
    private function signalsFor(int $topicId, Collection $rows, Collection $help, ?float $overallAvgTime): array
    {
        $graded = $rows->whereNotNull('is_correct');
        $wrongRate = $graded->isNotEmpty() ? $graded->where('is_correct', false)->count() / $graded->count() : 0.0;

        $byQuestion = $graded->groupBy('question_id');
        $repeated = $byQuestion->filter(fn ($q) => $q->where('is_correct', false)->count() >= 2)->count();
        $selfFixed = $byQuestion->filter(function ($q) {
            $firstWrong = $q->search(fn ($a) => $a->is_correct === false);

            return $firstWrong !== false && $q->slice($firstWrong + 1)->contains('is_correct', true);
        })->count();

        // Chập chờn: 6 lượt gần nhất đổi đúng↔sai nhiều lần — "hôm nay làm được, mai lại sai".
        $recent = $graded->take(-6)->pluck('is_correct')->values();
        $switches = 0;
        for ($i = 1; $i < $recent->count(); $i++) {
            $switches += $recent[$i] !== $recent[$i - 1] ? 1 : 0;
        }
        $unstable = $recent->count() >= 4 && $switches >= 3;

        $avgTime = $rows->where('time_spent_seconds', '>', 0)->avg('time_spent_seconds');
        $slow = $avgTime && $overallAvgTime && $avgTime > 1.5 * $overallAvgTime;

        $hints = $help->where('mode', 'hint')->count();
        $explains = $help->where('mode', 'explain')->count();

        $raw = self::WEIGHTS['wrong_rate'] * $wrongRate
            + self::WEIGHTS['repeated_errors'] * min(1, $repeated / 3)
            + self::WEIGHTS['hints'] * min(1, $hints / 5)
            + self::WEIGHTS['explains'] * min(1, $explains / 3)
            + self::WEIGHTS['slow'] * ($slow ? 1 : 0)
            + self::WEIGHTS['unstable'] * ($unstable ? 1 : 0)
            + self::WEIGHTS['self_fixed'] * min(1, $selfFixed / 3);

        $reasons = array_values(array_filter([
            $wrongRate >= 0.4 ? 'sai '.(int) round($wrongRate * 100).'% số câu gần đây' : null,
            $repeated > 0 ? "sai lặp lại {$repeated} câu" : null,
            $hints >= 3 ? "xin gợi ý {$hints} lần" : null,
            $explains >= 2 ? "xem lời giải {$explains} lần" : null,
            $slow ? 'làm chậm hơn hẳn các chủ đề khác' : null,
            $unstable ? 'lúc đúng lúc sai, chưa chắc' : null,
        ]));

        return [
            'topic_id' => $topicId,
            'attempts' => $rows->count(),
            'wrong_rate' => round($wrongRate, 2),
            'repeated_errors' => $repeated,
            'self_fixed' => $selfFixed,
            'hints' => $hints,
            'explains' => $explains,
            'slow' => (bool) $slow,
            'unstable' => $unstable,
            'need' => (int) max(0, min(100, round($raw))),
            'reasons' => $reasons,
        ];
    }

    /**
     * Lượt nhờ AI theo câu hỏi: gợi ý / giải thích, kèm chủ đề của câu.
     *
     * @return Collection<int, Collection<int, array{mode: string, question_id: int, at: mixed}>> keyBy topic_id
     */
    private function aiHelp(User $student, $since): Collection
    {
        return AiConversation::query()
            ->where('ai_conversations.user_id', $student->id)
            ->whereIn('mode', ['hint', 'explain'])
            ->where('context_type', 'question')
            ->where('ai_conversations.created_at', '>=', $since)
            ->join('questions', 'questions.id', '=', 'ai_conversations.context_id')
            ->get(['ai_conversations.mode', 'ai_conversations.context_id', 'ai_conversations.created_at', 'questions.topic_id'])
            ->map(fn ($c) => ['mode' => $c->mode, 'question_id' => (int) $c->context_id, 'at' => $c->created_at, 'topic_id' => (int) $c->topic_id])
            ->groupBy('topic_id');
    }
}
