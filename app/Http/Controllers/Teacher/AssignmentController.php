<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\StoreAssignmentRequest;
use App\Http\Requests\Teacher\UpdateAssignmentRequest;
use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Services\AuditLogger;
use App\Services\Teaching\AssignmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function __construct(
        private readonly AssignmentService $assignments,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $classIds = SchoolClass::query()->taughtBy($request->user())->pluck('id');

        return view('teacher.assignments.index', [
            'assignments' => Assignment::query()
                ->whereIn('class_id', $classIds)
                ->with('schoolClass')
                ->withCount([
                    'recipients',
                    'recipients as done_count' => fn ($q) => $q->where('status', '!=', 'assigned'),
                ])
                ->latest('published_at')
                ->paginate(20),
        ]);
    }

    /**
     * Form giao bài theo đúng thứ tự §17. Bước "chọn lớp" đi trước để các bước sau
     * (học sinh, đề, bài học, câu hỏi) chỉ hiện nội dung đúng khối lớp đó.
     */
    public function create(Request $request): View|RedirectResponse
    {
        $classes = SchoolClass::query()->taughtBy($request->user())->active()->with('grade')->get();

        if ($classes->isEmpty()) {
            return redirect()->route('teacher.classes.create')
                ->with('error', 'Hãy tạo lớp học trước khi giao bài.');
        }

        $class = $classes->firstWhere('id', $request->integer('class_id')) ?? $classes->first();
        $this->authorize('create', [Assignment::class, $class]);

        $user = $request->user();

        return view('teacher.assignments.create', [
            'classes' => $classes,
            'class' => $class,
            'students' => $class->activeStudents()->orderBy('name')->get(),
            'exams' => Exam::published()->where('grade_id', $class->grade_id)->orderBy('title')->get(),
            'lessons' => Lesson::published()
                ->whereHas('topic.chapter.subject', fn ($q) => $q->where('grade_id', $class->grade_id))
                ->with('topic')
                ->orderBy('title')
                ->get(),
            'questions' => Question::query()
                ->where('grade_id', $class->grade_id)
                ->where('type', '!=', Question::TYPE_ESSAY)
                ->where(fn ($q) => $q->where('status', 'published')->orWhere('created_by', $user->id))
                ->with('topic')
                ->latest('id')
                ->limit(100)
                ->get(),
        ]);
    }

    public function store(StoreAssignmentRequest $request): RedirectResponse
    {
        $class = SchoolClass::findOrFail($request->integer('class_id'));

        $assignment = $this->assignments->create($class, $request->user(), $request->validated());

        $this->audit->log('assignment.created', $assignment, null, [
            'type' => $assignment->type,
            'recipients' => $assignment->recipients()->count(),
        ]);

        return redirect()->route('teacher.assignments.show', $assignment)
            ->with('status', 'Đã giao bài.');
    }

    /** Theo dõi §17: Đã làm / Chưa làm, Điểm, Thời gian. */
    public function show(Request $request, Assignment $assignment): View
    {
        $this->authorize('manage', $assignment);

        $assignment->load('schoolClass', 'exam', 'lesson');

        $recipients = $assignment->recipients()
            ->with('student')
            ->get()
            ->sortBy([
                // Chưa làm lên đầu — đó là việc giáo viên cần xử lý.
                fn ($a, $b) => (int) $a->isDone() <=> (int) $b->isDone(),
                fn ($a, $b) => strcmp($a->student->name, $b->student->name),
            ])
            ->values();

        return view('teacher.assignments.show', [
            'assignment' => $assignment,
            'recipients' => $recipients,
            'doneCount' => $recipients->filter->isDone()->count(),
            'lateCount' => $recipients->where('is_late', true)->count(),
            'avgPercent' => ($scored = $recipients->whereNotNull('percent'))->isNotEmpty()
                ? (int) round($scored->avg('percent'))
                : null,
            'questionCount' => $assignment->type === Assignment::TYPE_QUESTION_SET
                ? $assignment->questions()->count()
                : null,
        ]);
    }

    public function update(UpdateAssignmentRequest $request, Assignment $assignment): RedirectResponse
    {
        $this->assignments->update($assignment, $request->validated());

        return back()->with('status', 'Đã cập nhật bài giao.');
    }

    public function toggleClosed(Assignment $assignment): RedirectResponse
    {
        $this->authorize('manage', $assignment);

        $this->assignments->toggleClosed($assignment);

        return back()->with('status', $assignment->isClosed() ? 'Đã đóng bài — không nhận nộp thêm.' : 'Đã mở lại bài.');
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $this->authorize('manage', $assignment);

        $this->audit->log('assignment.deleted', $assignment, ['title' => $assignment->title], null);
        $assignment->delete();

        return redirect()->route('teacher.assignments.index')->with('status', 'Đã xoá bài giao.');
    }
}
