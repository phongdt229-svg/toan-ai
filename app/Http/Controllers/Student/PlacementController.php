<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitPlacementRequest;
use App\Models\PlacementTest;
use App\Services\Learning\PlacementException;
use App\Services\Learning\PlacementTestService;
use App\Support\AiText;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** §34 Kiểm tra đầu vào. */
class PlacementController extends Controller
{
    public function __construct(private readonly PlacementTestService $placements) {}

    public function intro(Request $request): View
    {
        $student = $request->user()->load('studentProfile.grade');

        return view('student.placement.intro', [
            'grade' => $student->studentProfile?->grade,
            'latest' => $this->placements->latest($student),
            'questionCount' => PlacementTestService::TARGET_QUESTIONS,
            'minutes' => PlacementTestService::DURATION_MINUTES,
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        try {
            $test = $this->placements->start($request->user());
        } catch (PlacementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('student.placement.take', $test);
    }

    public function take(Request $request, PlacementTest $test): View|RedirectResponse
    {
        $this->ensureOwner($request, $test);

        if (! $test->isInProgress()) {
            return redirect()->route('student.placement.result', $test);
        }

        if ($test->isPastDeadline()) {
            $this->placements->submit($test, [], [], auto: true);

            return redirect()->route('student.placement.result', $test)
                ->with('error', 'Đã hết giờ. Bài đã được nộp tự động.');
        }

        $test->load('questions');

        return view('student.placement.take', [
            'test' => $test,
            // Dựng Question tạm từ bản chụp để dùng lại partial nhập đáp án của luyện tập.
            'questions' => $test->questions->map->toQuestion(),
            'remainingSeconds' => $test->remainingSeconds(),
        ]);
    }

    public function submit(SubmitPlacementRequest $request, PlacementTest $test): RedirectResponse
    {
        $data = $request->validated();

        $this->placements->submit($test, $data['answers'] ?? [], $data['time_spent'] ?? []);

        return redirect()->route('student.placement.result', $test);
    }

    public function result(Request $request, PlacementTest $test): View|RedirectResponse
    {
        $this->ensureOwner($request, $test);

        if ($test->isInProgress()) {
            return redirect()->route('student.placement.take', $test);
        }

        $test->load('questions.topic', 'answers');

        return view('student.placement.result', [
            'test' => $test,
            'analysisHtml' => $test->analysis ? AiText::toHtml($test->analysis) : null,
            'answers' => $test->answers->keyBy('placement_test_question_id'),
        ]);
    }

    private function ensureOwner(Request $request, PlacementTest $test): void
    {
        abort_unless($test->user_id === $request->user()->id, 403);
    }
}
