<?php

namespace App\Services\AI;

use App\Models\AiConversation;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\User;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AccessControlService;
use App\Services\FeatureLockedException;
use App\Services\Learning\GradingService;
use App\Services\SubscriptionService;
use App\Support\AiText;

/**
 * AI Tutor — 6 chế độ theo §10: Chat · Gợi ý · Giải thích · Kiểm tra đáp án · Bài tương tự · Phân tích lỗi.
 *
 * Mỗi lượt đi qua cùng một đường: kiểm tra quyền → kiểm tra quota → gọi provider
 * → ghi usage → lưu hội thoại. Lỗi provider không trừ quota của học sinh.
 */
class TutorService
{
    /** Các chế độ thuộc "AI nâng cao" (§18). */
    public const ADVANCED_MODES = ['analyze_mistake', 'similar_exercise'];

    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly PromptBuilder $prompts,
        private readonly AiUsageGuard $usage,
        private readonly TutorAccessGuard $access,
        private readonly GradingService $grading,
        private readonly AccessControlService $plans,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** @return array{conversation_id: int, reply_html: string} */
    public function chat(User $user, string $message, ?int $conversationId = null, string $contextType = 'free', ?int $contextId = null): array
    {
        $this->prepare($user);

        $conversation = $conversationId
            ? AiConversation::where('user_id', $user->id)->where('mode', 'chat')->findOrFail($conversationId)
            : AiConversation::create([
                'user_id' => $user->id,
                'mode' => 'chat',
                'context_type' => $contextType,
                'context_id' => $contextId,
                'title' => mb_substr($message, 0, 80),
            ]);

        $history = $conversation->messages()
            ->latest('id')
            ->limit(config('ai.chat_history_messages'))
            ->get()
            ->reverse()
            ->map(fn ($m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();

        $system = $this->prompts->system($user, 'chat');

        if ($conversation->context_type === 'lesson' && $conversation->context_id) {
            $lesson = Lesson::with('topic')->find($conversation->context_id);
            if ($lesson) {
                $system .= "\nHọc sinh đang học bài: \"{$lesson->title}\" (chủ đề {$lesson->topic->name}). Ưu tiên trả lời xoay quanh bài này.";
            }
        }

        $response = $this->call($user, 'chat', [
            ['role' => 'system', 'content' => $system],
            ...$history,
            ['role' => 'user', 'content' => $message],
        ]);

        $this->store($conversation, $message, $response);

        return ['conversation_id' => $conversation->id, 'reply_html' => AiText::toHtml($response->content)];
    }

    /** Gợi ý bước tiếp theo — không bao giờ kèm đáp án (§10). */
    public function hint(User $user, Question $question, ?string $work = null): array
    {
        $this->prepare($user, $question, 'hint');

        $userMessage = $this->prompts->describeQuestion($question)
            .($work ? "\n\nEm đã làm tới đây:\n{$work}" : "\n\nEm chưa biết bắt đầu từ đâu.");

        $response = $this->call($user, 'hint', [
            ['role' => 'system', 'content' => $this->prompts->system($user, 'hint')],
            ['role' => 'user', 'content' => $userMessage],
        ]);

        $this->log($user, 'hint', $question, $work ?? 'Xin gợi ý', $response);

        return ['reply_html' => AiText::toHtml($response->content)];
    }

    public function explain(User $user, Question $question): array
    {
        $this->prepare($user, $question, 'explain');

        $response = $this->call($user, 'explain', [
            ['role' => 'system', 'content' => $this->prompts->system($user, 'explain')],
            ['role' => 'user', 'content' => $this->prompts->describeQuestion($question)
                ."\n\nĐáp án đúng: ".$this->prompts->describeCorrectAnswer($question)
                .($question->explanation ? "\nLời giải của giáo viên: ".$this->prompts->plain($question->explanation) : '')
                ."\n\nGiải thích giúp em cách làm."],
        ]);

        $this->log($user, 'explain', $question, 'Giải thích câu này', $response);

        return ['reply_html' => AiText::toHtml($response->content)];
    }

    /**
     * Đúng/sai do GradingService quyết (từ đáp án trong DB) — AI chỉ nhận xét cách làm.
     * Không để model tự chấm: model có thể chấm sai, còn đáp án DB thì không.
     */
    public function checkAnswer(User $user, Question $question, mixed $answer, ?string $work = null): array
    {
        $this->prepare($user, $question, 'check_answer');

        $result = $this->grading->grade($question, $answer);
        $verdict = match (true) {
            $result->needsManualGrading => 'Câu tự luận — không có kết quả chấm tự động, hãy nhận xét bài làm.',
            $result->isCorrect => 'ĐÚNG',
            $result->score > 0 => 'ĐÚNG MỘT PHẦN',
            default => 'SAI',
        };

        $response = $this->call($user, 'check_answer', [
            ['role' => 'system', 'content' => $this->prompts->system($user, 'check_answer')],
            ['role' => 'user', 'content' => $this->prompts->describeQuestion($question)
                ."\n\nĐáp án của em: ".$this->prompts->describeAnswer($question, $answer)
                .($work ? "\nCách em làm:\n{$work}" : '')
                ."\n\nKết quả hệ thống chấm: {$verdict}"],
        ]);

        $this->log($user, 'check_answer', $question, 'Đáp án: '.$this->prompts->describeAnswer($question, $answer), $response);

        return [
            'is_correct' => $result->isCorrect,
            'verdict' => $verdict,
            'reply_html' => AiText::toHtml($response->content),
        ];
    }

    public function similarExercise(User $user, Question $question): array
    {
        $this->prepare($user, $question, 'similar_exercise');

        $response = $this->call($user, 'similar_exercise', [
            ['role' => 'system', 'content' => $this->prompts->system($user, 'similar_exercise')],
            ['role' => 'user', 'content' => $this->prompts->describeQuestion($question)."\n\nTạo một bài tương tự."],
        ], json: true);

        $data = $this->requireKeys($response->json(), ['problem', 'answer', 'solution']);

        $this->log($user, 'similar_exercise', $question, 'Bài tương tự', $response);

        return [
            'problem_html' => AiText::toHtml($data['problem']),
            'answer_html' => AiText::toHtml($data['answer']),
            'solution_html' => AiText::toHtml($data['solution']),
        ];
    }

    /** §10: Làm sai → Phân tích lỗi → Xác định kiến thức sai → Giải thích → Gợi ý. */
    public function analyzeMistake(User $user, Question $question, mixed $answer): array
    {
        $this->prepare($user, $question, 'analyze_mistake');

        $response = $this->call($user, 'analyze_mistake', [
            ['role' => 'system', 'content' => $this->prompts->system($user, 'analyze_mistake')],
            ['role' => 'user', 'content' => $this->prompts->describeQuestion($question)
                ."\n\nĐáp án đúng: ".$this->prompts->describeCorrectAnswer($question)
                ."\nĐáp án của em: ".$this->prompts->describeAnswer($question, $answer)],
        ], json: true);

        $data = $this->requireKeys($response->json(), ['misconception', 'knowledge_gap', 'explanation', 'hint']);

        $this->log($user, 'analyze_mistake', $question, 'Phân tích lỗi', $response);

        return [
            'misconception_html' => AiText::toHtml($data['misconception']),
            'knowledge_gap' => mb_substr(strip_tags((string) $data['knowledge_gap']), 0, 120),
            'explanation_html' => AiText::toHtml($data['explanation']),
            'hint_html' => AiText::toHtml($data['hint']),
            'topic' => $question->topic ? ['id' => $question->topic->id, 'name' => $question->topic->name] : null,
        ];
    }

    // ------------------------------------------------------------------------

    private function prepare(User $user, ?Question $question = null, ?string $mode = null): void
    {
        $user->loadMissing('studentProfile.grade');

        $this->access->ensureCanUseTutor($user);

        if ($question && $mode) {
            $question->loadMissing('options', 'topic');
            $this->access->ensureQuestionMode($user, $question, $mode);
        }

        // §18: "AI nâng cao" thuộc gói trả phí.
        if (in_array($mode, self::ADVANCED_MODES, true) && ! $this->plans->allows($user, 'ai.advanced_modes')) {
            throw new FeatureLockedException(
                'Phân tích lỗi và Bài tương tự là tính năng AI nâng cao. Nâng cấp gói để dùng nhé.',
                $this->subscriptions->cheapestPackageAllowing('ai.advanced_modes'),
            );
        }

        $this->usage->ensureAllowed($user);
    }

    /** @param  array<int, array{role: string, content: string}>  $messages */
    private function call(User $user, string $mode, array $messages, bool $json = false): AiResponse
    {
        try {
            $response = $this->provider->complete(new AiRequest(
                messages: $messages,
                task: $mode,
                maxTokens: config("ai.max_output_tokens.{$mode}", 700),
                // Gợi ý cần ổn định; bài tương tự cần đa dạng.
                temperature: $mode === 'similar_exercise' ? 0.8 : 0.3,
                json: $json,
            ));
        } catch (AiProviderException $e) {
            $this->usage->recordFailure($user, $mode);

            throw $e;
        }

        $this->usage->record($user, $mode, $response);

        return $response;
    }

    private function store(AiConversation $conversation, string $userMessage, AiResponse $response): void
    {
        $conversation->messages()->create(['role' => 'user', 'content' => $userMessage]);
        $conversation->messages()->create([
            'role' => 'assistant',
            'content' => $response->content,
            'model' => $response->model,
            'tokens_in' => $response->tokensIn,
            'tokens_out' => $response->tokensOut,
            'latency_ms' => $response->latencyMs,
        ]);
        $conversation->update(['last_message_at' => now()]);
    }

    /** Các chế độ theo câu hỏi: mỗi lượt là một hội thoại ngắn gắn với câu hỏi đó. */
    private function log(User $user, string $mode, Question $question, string $userMessage, AiResponse $response): void
    {
        $conversation = AiConversation::create([
            'user_id' => $user->id,
            'mode' => $mode,
            'context_type' => 'question',
            'context_id' => $question->id,
            'title' => mb_substr($this->prompts->plain($question->content), 0, 80),
        ]);

        $this->store($conversation, $userMessage, $response);
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  array<int, string>  $keys
     * @return array<string, string>
     */
    private function requireKeys(array $data, array $keys): array
    {
        foreach ($keys as $key) {
            if (! isset($data[$key]) || ! is_scalar($data[$key]) || trim((string) $data[$key]) === '') {
                throw new AiProviderException('AI trả về thiếu nội dung, bạn thử lại nhé.', 'invalid_json');
            }
        }

        return array_map('strval', array_intersect_key($data, array_flip($keys)));
    }
}
