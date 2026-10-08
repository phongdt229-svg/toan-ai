<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStudyScheduleRequest;
use App\Models\User;
use App\Services\Learning\StudyScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

/** Phụ huynh đặt / sửa lịch học tuần cho con đã liên kết (D-01). */
class StudyScheduleController extends Controller
{
    public function __construct(private readonly StudyScheduleService $schedules) {}

    public function edit(Request $request, User $student): View
    {
        Gate::authorize('manage-study-schedule', $student);

        $schedule = $this->schedules->forStudent($student);

        return view('parent.children.schedule', [
            'student' => $student,
            'schedule' => $schedule,
            'weeklyMinutes' => $this->schedules->weeklyMinutes($schedule),
        ]);
    }

    public function update(UpdateStudyScheduleRequest $request, User $student): RedirectResponse
    {
        $this->schedules->replace($student, $request->slots(), $request->user());

        return back()->with('status', "Đã lưu lịch học của {$student->name}.");
    }
}
