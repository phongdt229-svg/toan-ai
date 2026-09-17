<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SaveExamAnswerRequest;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\AccessControlService;
use App\Services\Learning\ExamException;
use App\Services\Learning\ExamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamService $exams,
        private readonly AccessControlService $access,
    ) {}

    /** Đề đang mở cho lớp của học sinh, kèm tình trạng các lượt đã làm. */
    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile');
        $gradeId = $user->studentProfile?->grade_id;

        $exams = Exam::query()
            ->published()
            ->open()
            ->when($gradeId, fn ($q) => $q->where('grade_id', $gradeId))
            ->withCount(['attempts as my_attempts_count' => fn ($q) => $q->where('user_id', $user->id)])
            ->withMax(['attempts as my_best_score' => fn ($q) => $q->where('user_id', $user->id)], 'score')
            ->latest()
            ->get();

        $inProgress = ExamAttempt::query()
            ->where('user_id', $user->id)
            ->where('status', ExamAttempt::STATUS_IN_PROGRESS)
            ->pluck('exam_id')
            ->flip();

        return view('student.exams.index', [
            'exams' => $exams,
            'inProgress' => $inProgress,
        ]);
    }

    public function show(Request $request, Exam $exam): View
    {
        $this->authorize('take', $exam);

        $user = $request->user();

        return view('student.exams.show', [
            'exam' => $exam->load('grade'),
            'attempts' => $exam->attempts()->where('user_id', $user->id)->latest('attempt_no')->get(),
            'isLocked' => ! $this->access->canAccessLevel($user, $exam->access_level),
        ]);
    }

    public function start(Request $request, Exam $exam): RedirectResponse
    {
        $this->authorize('take', $exam);

        try {
            $attempt = $this->exams->start($request->user(), $exam);
        } catch (ExamException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('student.exams.take', $attempt);
    }

    public function take(Request $request, ExamAttempt $attempt): View|RedirectResponse
    {
        $this->authorize('view', $attempt);

        if ($attempt->isFinished()) {
            return redirect()->route('student.exams.result', $attempt);
        }

        // Học sinh mở lại tab sau khi hết giờ → chốt bài ngay tại đây.
        if ($attempt->isPastDeadline()) {
            $this->exams->submit($attempt, auto: true);

            return redirect()->route('student.exams.result', $attempt)
                ->with('error', 'Đã hết giờ. Bài của bạn đã được nộp tự động.');
        }

        $attempt->load('exam', 'answers');

        return view('student.exams.take', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'questions' => $this->exams->questionsForAttempt($attempt),
            'saved' => $attempt->answers->mapWithKeys(fn ($a) => [$a->question_id => $a->value()])->all(),
            // Gửi số giây còn lại thay vì mốc giờ: đồng hồ máy học sinh có thể lệch.
            'remainingSeconds' => $attempt->remainingSeconds(),
        ]);
    }

    public function saveAnswer(SaveExamAnswerRequest $request, ExamAttempt $attempt): JsonResponse
    {
        try {
            $this->exams->saveAnswer(
                $attempt,
                $request->integer('question_id'),
                $request->input('value'),
                $request->integer('time_spent'),
            );
        } catch (ExamException $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
                'redirect' => route('student.exams.result', $attempt),
            ], 409);
        }

        return response()->json([
            'success' => true,
            'data' => ['remaining_seconds' => $attempt->remainingSeconds()],
        ]);
    }

    public function submit(Request $request, ExamAttempt $attempt): RedirectResponse
    {
        $this->authorize('view', $attempt);

        $this->exams->submit($attempt, auto: $attempt->isPastDeadline());

        return redirect()->route('student.exams.result', $attempt)
            ->with('status', 'Đã nộp bài.');
    }

    public function result(Request $request, ExamAttempt $attempt): View|RedirectResponse
    {
        $this->authorize('view', $attempt);

        if ($attempt->isInProgress()) {
            return redirect()->route('student.exams.take', $attempt);
        }

        $attempt->load('exam', 'answers');

        return view('student.exams.result', [
            'attempt' => $attempt,
            'exam' => $attempt->exam,
            'questions' => $this->exams->questionsForAttempt($attempt),
            'answers' => $attempt->answers->keyBy('question_id'),
            'breakdown' => $this->exams->breakdownByTopic($attempt),
            'revealAnswers' => $attempt->exam->answersRevealable(),
        ]);
    }
}
