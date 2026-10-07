<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitQuizRequest;
use App\Models\StudySession;
use App\Services\Learning\LearningPathService;
use App\Services\Learning\PlacementException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** §35 Lộ trình học · §37 Kiểm tra cuối buổi. */
class LearningPathController extends Controller
{
    public function __construct(private readonly LearningPathService $paths) {}

    public function show(Request $request): View|RedirectResponse
    {
        $path = $this->paths->active($request->user());

        if (! $path) {
            return redirect()->route('student.placement.intro')
                ->with('status', 'Làm bài kiểm tra đầu vào để hệ thống xếp lộ trình riêng cho em.');
        }

        $path->load('stages.items.topic', 'placementTest', 'sessions');

        return view('student.path.show', [
            'path' => $path,
            'current' => $this->paths->currentSession($path),
            'service' => $this->paths,
        ]);
    }

    public function quiz(Request $request, StudySession $session): View|RedirectResponse
    {
        $this->ensureOwner($request, $session);

        // Mở lại đề đã quá giờ → chốt luôn (cron cũng chốt, nhưng học sinh quay lại sớm hơn cron chạy).
        if ($session->status === StudySession::STATUS_QUIZ_PENDING && $session->quizIsOverdue()) {
            $result = $this->paths->submitQuiz($session, [], auto: true);

            return redirect()->route('student.path.show')->with('error', $this->resultMessage($session, $result));
        }

        try {
            $questions = $this->paths->quizQuestions($session);
        } catch (PlacementException $e) {
            return redirect()->route('student.path.show')->with('error', $e->getMessage());
        }

        if ($questions->isEmpty()) {
            return redirect()->route('student.path.show')
                ->with('status', "Hoàn thành buổi {$session->session_no}!");
        }

        return view('student.path.quiz', [
            'session' => $session->refresh(),
            'questions' => $questions,
        ]);
    }

    public function submitQuiz(SubmitQuizRequest $request, StudySession $session): RedirectResponse
    {
        $data = $request->validated();

        try {
            $result = $this->paths->submitQuiz($session, $data['answers'] ?? [], $data['time_spent'] ?? []);
        } catch (PlacementException $e) {
            return redirect()->route('student.path.show')->with('error', $e->getMessage());
        }

        return redirect()->route('student.path.show')
            ->with($result['auto'] ? 'error' : 'status', $this->resultMessage($session, $result));
    }

    /** @param  array{percent: int, passed: bool, review_topics: array<int, string>, next: string, auto: bool}  $result */
    private function resultMessage(StudySession $session, array $result): string
    {
        if ($result['auto']) {
            return "Bài kiểm tra cuối buổi {$session->session_no} đã quá ".LearningPathService::QUIZ_MINUTES
                .' phút nên câu trả lời gửi trễ không được chấm. Buổi sau có thêm phần ôn cho các chủ đề này.';
        }

        $topics = implode(', ', $result['review_topics']);

        return match (true) {
            $result['next'] === LearningPathService::NEXT_REVIEW_FIRST && $topics !== '' => "Kiểm tra cuối buổi đạt {$result['percent']}%. Buổi tiếp theo là buổi ôn: {$topics} — ôn chắc rồi mình học bài mới nhé.",
            $result['next'] === LearningPathService::NEXT_REVIEW_SHARE && $topics !== '' => "Hoàn thành buổi {$session->session_no} — đạt {$result['percent']}%. Buổi sau học bài mới, kèm phần ôn: {$topics}.",
            default => "Hoàn thành buổi {$session->session_no} — kiểm tra cuối buổi đạt {$result['percent']}%.",
        };
    }

    private function ensureOwner(Request $request, StudySession $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 403);
    }
}
