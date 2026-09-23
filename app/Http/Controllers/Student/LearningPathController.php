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

        try {
            $questions = $this->paths->quizQuestions($session);
        } catch (PlacementException $e) {
            return redirect()->route('student.path.show')->with('error', $e->getMessage());
        }

        if ($questions->isEmpty()) {
            return redirect()->route('student.path.show')
                ->with('status', "Hoàn thành buổi {$session->session_no}!");
        }

        return view('student.path.quiz', ['session' => $session, 'questions' => $questions]);
    }

    public function submitQuiz(SubmitQuizRequest $request, StudySession $session): RedirectResponse
    {
        $data = $request->validated();

        try {
            $result = $this->paths->submitQuiz($session, $data['answers'] ?? []);
        } catch (PlacementException $e) {
            return redirect()->route('student.path.show')->with('error', $e->getMessage());
        }

        $message = $result['passed']
            ? "Hoàn thành buổi {$session->session_no} — kiểm tra cuối buổi đạt {$result['percent']}%."
            : "Kiểm tra cuối buổi đạt {$result['percent']}%. Buổi sau có thêm phần ôn: ".implode(', ', $result['review_topics']).'.';

        return redirect()->route('student.path.show')->with('status', $message);
    }

    private function ensureOwner(Request $request, StudySession $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 403);
    }
}
