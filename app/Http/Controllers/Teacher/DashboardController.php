<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ExamAttempt;
use App\Models\SchoolClass;
use App\Services\Teaching\StudentInsightService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly StudentInsightService $insights) {}

    /** §13: Số lớp, Số học sinh, Bài tập đang giao, Điểm trung bình, Học sinh cần hỗ trợ. */
    public function index(Request $request): View
    {
        $teacher = $request->user()->load('teacherProfile');
        $classIds = SchoolClass::query()->taughtBy($teacher)->pluck('id');

        return view('teacher.dashboard', [
            'user' => $teacher,
            'stats' => $this->insights->dashboardStats($teacher),
            'needSupport' => $this->insights->filter($this->insights->studentsFor($teacher), 'needs_support')->take(5),
            'dueSoon' => Assignment::query()
                ->whereIn('class_id', $classIds)
                ->where('status', Assignment::STATUS_PUBLISHED)
                ->whereBetween('due_at', [now(), now()->addDays(7)])
                ->with('schoolClass')
                ->withCount([
                    'recipients',
                    'recipients as done_count' => fn ($q) => $q->where('status', '!=', 'assigned'),
                ])
                ->orderBy('due_at')
                ->limit(5)
                ->get(),
            'pendingGrading' => ExamAttempt::query()
                ->where('status', ExamAttempt::STATUS_SUBMITTED)
                ->whereHas('exam', fn ($q) => $q->where('created_by', $teacher->id))
                ->count(),
        ]);
    }
}
