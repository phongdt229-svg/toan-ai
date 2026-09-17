<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\TeacherCommentRequest;
use App\Models\AssignmentStudent;
use App\Models\SchoolClass;
use App\Models\TeacherComment;
use App\Models\User;
use App\Services\Learning\MasteryService;
use App\Services\Teaching\StudentInsightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StudentController extends Controller
{
    public function __construct(
        private readonly StudentInsightService $insights,
        private readonly MasteryService $mastery,
    ) {}

    /** Danh sách học sinh mọi lớp giáo viên dạy, kèm 5 bộ lọc §13. */
    public function index(Request $request): View
    {
        $teacher = $request->user();
        abort_unless($teacher->hasPermission('progress.view.student'), 403);

        $filter = $request->string('filter')->toString() ?: 'all';
        $all = $this->insights->studentsFor($teacher);

        return view('teacher.students.index', [
            'filter' => $filter,
            'filterCounts' => collect(StudentInsightService::FILTERS)
                ->map(fn ($label, $key) => $this->insights->filter($all, $key)->count()),
            'students' => $this->insights->filter($all, $filter),
        ]);
    }

    public function show(Request $request, User $student): View
    {
        $teacher = $request->user();
        abort_unless($teacher->teachesStudent($student), 403);

        $classIds = SchoolClass::query()->taughtBy($teacher)->pluck('id');
        $insight = $this->insights->studentsFor($teacher)->firstWhere('student.id', $student->id);

        return view('teacher.students.show', [
            'student' => $student->load('studentProfile.grade'),
            'insight' => $insight,
            'classes' => SchoolClass::query()->whereIn('id', $classIds)
                ->whereHas('activeStudents', fn ($q) => $q->where('users.id', $student->id))
                ->get(),
            'records' => AssignmentStudent::query()
                ->where('student_id', $student->id)
                ->whereHas('assignment', fn ($q) => $q->whereIn('class_id', $classIds)->whereNull('deleted_at'))
                ->with('assignment')
                ->latest('id')
                ->get(),
            'weakTopics' => $this->mastery->weakTopics($student),
            'strongTopics' => $this->mastery->strongTopics($student),
            'comments' => TeacherComment::query()
                ->where('student_id', $student->id)
                ->with('teacher')
                ->latest()
                ->limit(20)
                ->get(),
        ]);
    }

    public function storeComment(TeacherCommentRequest $request, User $student): RedirectResponse
    {
        TeacherComment::create([
            ...$request->validated(),
            'teacher_id' => $request->user()->id,
            'student_id' => $student->id,
        ]);

        return back()->with('status', 'Đã lưu nhận xét.');
    }

    public function destroyComment(Request $request, TeacherComment $comment): RedirectResponse
    {
        // Chỉ người viết mới xoá được nhận xét của mình.
        abort_unless($comment->teacher_id === $request->user()->id, 403);

        $comment->delete();

        return back()->with('status', 'Đã xoá nhận xét.');
    }
}
