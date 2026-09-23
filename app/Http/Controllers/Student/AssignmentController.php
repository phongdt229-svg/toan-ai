<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\SubmitAssignmentRequest;
use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\AssignmentSubmission;
use App\Services\Learning\AssignmentSubmissionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class AssignmentController extends Controller
{
    public function __construct(private readonly AssignmentSubmissionService $submissions) {}

    public function index(Request $request): View
    {
        $records = AssignmentStudent::query()
            ->where('student_id', $request->user()->id)
            ->whereHas('assignment', fn ($q) => $q->whereNull('deleted_at'))
            ->with('assignment.schoolClass')
            ->get();

        // Chưa làm, sắp hết hạn lên trước; bài đã xong xếp sau theo lúc làm.
        $pending = $records->reject->isDone()
            ->sortBy(fn ($r) => $r->assignment->due_at?->timestamp ?? PHP_INT_MAX)
            ->values();
        $done = $records->filter->isDone()->sortByDesc('completed_at')->values();

        return view('student.assignments.index', compact('pending', 'done'));
    }

    public function show(Request $request, Assignment $assignment): View|RedirectResponse
    {
        $this->authorize('work', $assignment);

        $record = $this->record($request, $assignment);
        $assignment->load('schoolClass', 'teacher', 'exam', 'lesson');

        return view('student.assignments.show', [
            'assignment' => $assignment,
            'record' => $record,
            'blockReason' => $this->submissions->blockReason($assignment, $record),
            'questions' => $assignment->type === Assignment::TYPE_QUESTION_SET
                ? $this->submissions->questionsFor($assignment)
                : collect(),
            'latestSubmission' => AssignmentSubmission::query()
                ->where('assignment_id', $assignment->id)
                ->where('student_id', $request->user()->id)
                ->latest('attempt_no')
                ->first(),
        ]);
    }

    public function submit(SubmitAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        abort_unless($assignment->type === Assignment::TYPE_QUESTION_SET, 404);

        $data = $request->validated();

        try {
            $submission = $this->submissions->submit(
                $assignment,
                $request->user(),
                $data['answers'] ?? [],
                $data['time_spent'] ?? [],
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('student.assignments.result', [$assignment, $submission])
            ->with('status', 'Đã nộp bài.');
    }

    public function result(Request $request, Assignment $assignment, AssignmentSubmission $submission): View
    {
        $this->authorize('work', $assignment);
        abort_unless(
            $submission->assignment_id === $assignment->id && $submission->student_id === $request->user()->id,
            404,
        );

        return view('student.assignments.result', [
            'assignment' => $assignment,
            'submission' => $submission,
            'record' => $this->record($request, $assignment),
            'questions' => $this->submissions->questionsFor($assignment),
        ]);
    }

    private function record(Request $request, Assignment $assignment): ?AssignmentStudent
    {
        return AssignmentStudent::query()
            ->where('assignment_id', $assignment->id)
            ->where('student_id', $request->user()->id)
            ->first();
    }
}
