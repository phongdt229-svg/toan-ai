<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        // Phase 5 sẽ nạp số liệu thật (§13).
        return view('teacher.dashboard', [
            'user' => $request->user()->load('teacherProfile'),
            'stats' => [
                'classes' => 0,
                'students' => 0,
                'open_assignments' => 0,
                'average_score' => null,
                'students_needing_help' => 0,
            ],
        ]);
    }
}
