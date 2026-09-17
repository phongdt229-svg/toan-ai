<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\StudentAnswer;
use App\Services\Learning\ExamService;
use App\Services\Teaching\ExamGradingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use InvalidArgumentException;

class ExamGradingController extends Controller
{
    public function __construct(
        private readonly ExamGradingService $grading,
        private readonly ExamService $exams,
    ) {}

    /** Danh sách lượt làm của một đề — lọc nhanh bài còn chờ chấm. */
    public function index(Request $request, Exam $exam): View
    {
        $this->authorize('grade', $exam);

        $attempts = $exam->attempts()
            ->with('user')
            ->where('status', '!=', ExamAttempt::STATUS_IN_PROGRESS)
            ->when($request->boolean('pending'), fn ($q) => $q->where('status', ExamAttempt::STATUS_SUBMITTED))
            ->latest('submitted_at')
            ->paginate(30)
            ->withQueryString();

        return view('teacher.exams.attempts', [
            'exam' => $exam,
            'attempts' => $attempts,
        ]);
    }

    public function show(ExamAttempt $attempt): View
    {
        $attempt->load('exam', 'user', 'answers');
        $this->authorize('grade', $attempt->exam);

        return view('teacher.exams.grade', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'questions' => $this->exams->questionsForAttempt($attempt),
            'answers' => $attempt->answers->keyBy('question_id'),
        ]);
    }

    public function grade(Request $request, StudentAnswer $answer): RedirectResponse
    {
        $answer->load('attempt.exam');
        $this->authorize('grade', $answer->attempt->exam);

        $data = $request->validate([
            'score' => ['required', 'numeric', 'min:0'],
            'feedback' => ['nullable', 'string', 'max:2000'],
        ], [], ['score' => 'điểm', 'feedback' => 'nhận xét']);

        try {
            $this->grading->grade($answer, (float) $data['score'], $data['feedback'] ?? null, $request->user());
        } catch (InvalidArgumentException $e) {
            return back()->withErrors(["score.{$answer->id}" => $e->getMessage()]);
        }

        return back()->with('status', 'Đã chấm câu trả lời.');
    }
}
