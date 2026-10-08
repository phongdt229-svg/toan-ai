<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStudyScheduleRequest;
use App\Services\Learning\StudyScheduleService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Lịch học tuần của chính học sinh (D-01). */
class StudyScheduleController extends Controller
{
    public function __construct(private readonly StudyScheduleService $schedules) {}

    public function edit(Request $request): View
    {
        $schedule = $this->schedules->forStudent($request->user());

        return view('student.schedule', [
            'schedule' => $schedule,
            'weeklyMinutes' => $this->schedules->weeklyMinutes($schedule),
        ]);
    }

    public function update(UpdateStudyScheduleRequest $request): RedirectResponse
    {
        $this->schedules->replace($request->user(), $request->slots(), $request->user());

        return back()->with('status', 'Đã lưu lịch học.');
    }
}
