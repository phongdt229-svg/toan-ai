<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AiQuestionRequest;
use App\Models\AiConversation;
use App\Models\AssignmentStudent;
use App\Models\ExamAttempt;
use App\Models\Question;
use App\Models\User;
use App\Services\AI\AiProviderException;
use App\Services\AI\AiQuotaExceededException;
use App\Services\AI\AiUsageGuard;
use App\Services\AI\TutorBlockedException;
use App\Services\AI\TutorService;
use App\Services\FeatureLockedException;
use App\Support\AiText;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * §10 — /api/v1/ai/*. Xác thực bằng session (cùng domain), nên dùng được từ widget trên trang.
 */
class AiController extends Controller
{
    public function __construct(
        private readonly TutorService $tutor,
        private readonly AiUsageGuard $usage,
    ) {}

    public function chat(Request $request): JsonResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'conversation_id' => ['nullable', 'integer'],
            'context_type' => ['nullable', Rule::in(['lesson', 'free'])],
            'context_id' => ['nullable', 'integer'],
        ], [], ['message' => 'câu hỏi']);

        return $this->respond($request, fn () => $this->tutor->chat(
            $request->user(),
            $data['message'],
            $data['conversation_id'] ?? null,
            $data['context_type'] ?? 'free',
            $data['context_id'] ?? null,
        ));
    }

    public function hint(AiQuestionRequest $request): JsonResponse
    {
        return $this->respond($request, fn () => $this->tutor->hint(
            $request->user(), $this->question($request), $request->input('work'),
        ));
    }

    public function explain(AiQuestionRequest $request): JsonResponse
    {
        return $this->respond($request, fn () => $this->tutor->explain($request->user(), $this->question($request)));
    }

    public function checkAnswer(AiQuestionRequest $request): JsonResponse
    {
        return $this->respond($request, fn () => $this->tutor->checkAnswer(
            $request->user(), $this->question($request), $request->input('answer'), $request->input('work'),
        ));
    }

    public function similarExercise(AiQuestionRequest $request): JsonResponse
    {
        return $this->respond($request, fn () => $this->tutor->similarExercise($request->user(), $this->question($request)));
    }

    public function analyzeMistake(AiQuestionRequest $request): JsonResponse
    {
        return $this->respond($request, fn () => $this->tutor->analyzeMistake(
            $request->user(), $this->question($request), $request->input('answer'),
        ));
    }

    public function usage(Request $request): JsonResponse
    {
        return response()->json(['success' => true, 'data' => $this->usage->status($request->user())]);
    }

    public function conversations(Request $request): JsonResponse
    {
        $items = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->where('mode', 'chat')
            ->latest('last_message_at')
            ->limit(20)
            ->get(['id', 'title', 'last_message_at']);

        return response()->json(['success' => true, 'data' => $items]);
    }

    public function conversation(Request $request, int $id): JsonResponse
    {
        $conversation = AiConversation::query()
            ->where('user_id', $request->user()->id)
            ->with('messages')
            ->findOrFail($id);

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $conversation->id,
                'title' => $conversation->title,
                'messages' => $conversation->messages->map(fn ($m) => [
                    'role' => $m->role,
                    // Tin nhắn của học sinh cũng escape — không in lại HTML người dùng gõ.
                    'html' => $m->role === 'assistant' ? AiText::toHtml($m->content) : nl2br(e($m->content), false),
                ]),
            ],
        ]);
    }

    /**
     * Học sinh chỉ hỏi được về câu mình thực sự được tiếp cận: câu đã xuất bản, câu trong đề mình đã làm,
     * hoặc câu trong bài được giao cho mình. Chặn dò id để đọc câu nháp của giáo viên.
     */
    private function question(Request $request): Question
    {
        /** @var User $user */
        $user = $request->user();
        $question = Question::findOrFail($request->integer('question_id'));

        if (! $user->isStudent() || $question->status === 'published') {
            return $question;
        }

        $reachable = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->whereHas('exam.questions', fn ($q) => $q->where('questions.id', $question->id))
            ->exists()
            || AssignmentStudent::query()
                ->where('student_id', $user->id)
                ->whereHas('assignment.questions', fn ($q) => $q->where('questions.id', $question->id))
                ->exists();

        abort_unless($reachable, 404);

        return $question;
    }

    private function respond(Request $request, Closure $action): JsonResponse
    {
        try {
            $data = $action();
        } catch (TutorBlockedException $e) {
            return $this->error($e->getMessage(), 'blocked', 403);
        } catch (FeatureLockedException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'reason' => 'upgrade_required',
                'upgrade' => $e->upgradeTo ? [
                    'name' => $e->upgradeTo->name,
                    'price' => $e->upgradeTo->priceLabel(),
                    'url' => route('packages.index'),
                ] : null,
            ], 402);
        } catch (AiQuotaExceededException $e) {
            return $this->error($e->getMessage(), 'quota_exceeded', 429);
        } catch (AiProviderException $e) {
            return $this->error($e->getMessage(), $e->reason, 503);
        }

        return response()->json([
            'success' => true,
            'data' => $data,
            'meta' => ['usage' => $this->usage->status($request->user())],
        ]);
    }

    private function error(string $message, string $reason, int $status): JsonResponse
    {
        return response()->json(['success' => false, 'message' => $message, 'reason' => $reason], $status);
    }
}
