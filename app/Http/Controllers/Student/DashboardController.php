<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\StudentLessonProgress;
use App\Services\Learning\ProgressService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly ProgressService $progress) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile.grade');

        $recent = StudentLessonProgress::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', StudentLessonProgress::STATUS_COMPLETED)
            ->with('lesson.topic')
            ->latest('last_viewed_at')
            ->limit(3)
            ->get();

        return view('student.dashboard', [
            'user' => $user,
            'grade' => $user->studentProfile?->grade,
            'stats' => $this->progress->summaryFor($user),
            'topicProgress' => $this->progress->progressByTopic($user, 5),
            'continueLearning' => $recent,
        ]);
    }
}
