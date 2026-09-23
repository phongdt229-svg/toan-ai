<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\AddAssistantRequest;
use App\Http\Requests\Teacher\AddStudentToClassRequest;
use App\Http\Requests\Teacher\ClassRequest;
use App\Models\Grade;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Teaching\ClassService;
use App\Services\Teaching\StudentInsightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function __construct(
        private readonly ClassService $classes,
        private readonly StudentInsightService $insights,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', SchoolClass::class);

        return view('teacher.classes.index', [
            'classes' => SchoolClass::query()
                ->taughtBy($request->user())
                ->with('grade')
                ->withCount('activeStudents')
                ->withCount(['assignments' => fn ($q) => $q->where('status', 'published')])
                ->orderBy('status')
                ->latest()
                ->get(),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', SchoolClass::class);

        return view('teacher.classes.create', [
            'class' => new SchoolClass,
            'grades' => Grade::active()->ordered()->get(),
        ]);
    }

    public function store(ClassRequest $request): RedirectResponse
    {
        $class = $this->classes->create($request->validated(), $request->user());

        $this->audit->log('class.created', $class, null, ['name' => $class->name]);

        return redirect()->route('teacher.classes.show', $class)
            ->with('status', "Đã tạo lớp. Mã tham gia: {$class->code}");
    }

    public function show(Request $request, SchoolClass $class): View
    {
        $this->authorize('view', $class);

        $class->load('grade', 'teachers');
        $filter = $request->string('filter')->toString() ?: 'all';
        $insights = $this->insights->studentsFor($request->user(), $class->id);

        return view('teacher.classes.show', [
            'class' => $class,
            'grades' => Grade::active()->ordered()->get(),
            'isOwner' => $class->isOwnedBy($request->user()),
            'filter' => $filter,
            'filterCounts' => collect(StudentInsightService::FILTERS)
                ->map(fn ($label, $key) => $this->insights->filter($insights, $key)->count()),
            'students' => $this->insights->filter($insights, $filter),
            'assignments' => $class->assignments()
                ->withCount(['recipients', 'recipients as done_count' => fn ($q) => $q->where('status', '!=', 'assigned')])
                ->latest('published_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function update(ClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $class->update($request->validated());

        return back()->with('status', 'Đã lưu thông tin lớp.');
    }

    public function toggleArchive(SchoolClass $class): RedirectResponse
    {
        $this->authorize('delete', $class);

        $archived = $class->status === SchoolClass::STATUS_ARCHIVED;
        $class->update(['status' => $archived ? SchoolClass::STATUS_ACTIVE : SchoolClass::STATUS_ARCHIVED]);

        $this->audit->log($archived ? 'class.restored' : 'class.archived', $class);

        return back()->with('status', $archived ? 'Đã mở lại lớp.' : 'Đã lưu trữ lớp — học sinh không thể tham gia bằng mã nữa.');
    }

    public function regenerateCode(SchoolClass $class): RedirectResponse
    {
        $this->authorize('update', $class);

        $code = $this->classes->regenerateCode($class);

        return back()->with('status', "Mã lớp mới: {$code}. Mã cũ không còn dùng được.");
    }

    public function addStudent(AddStudentToClassRequest $request, SchoolClass $class): RedirectResponse
    {
        $student = $this->classes->addStudentByEmail($class, $request->validated('email'));

        return back()->with('status', "Đã thêm {$student->name} vào lớp.");
    }

    public function removeStudent(SchoolClass $class, User $student): RedirectResponse
    {
        $this->authorize('manageStudents', $class);

        $this->classes->removeStudent($class, $student);
        $this->audit->log('class.student_removed', $class, null, ['student_id' => $student->id]);

        return back()->with('status', "Đã xoá {$student->name} khỏi lớp. Điểm và bài làm cũ vẫn được giữ.");
    }

    public function addAssistant(AddAssistantRequest $request, SchoolClass $class): RedirectResponse
    {
        $teacher = $this->classes->addAssistant($class, $request->validated('email'));

        return back()->with('status', "Đã thêm giáo viên {$teacher->name} vào lớp.");
    }
}
