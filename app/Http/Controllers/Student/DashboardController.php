<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile.grade');

        // Phase 2–3 sẽ thay các số 0 này bằng dữ liệu thật từ ProgressService.
        return view('student.dashboard', [
            'user' => $user,
            'grade' => $user->studentProfile?->grade,
            'stats' => [
                'lessons_completed' => 0,
                'exercises_done' => 0,
                'study_minutes' => 0,
                'average_score' => null,
            ],
        ]);
    }
}
