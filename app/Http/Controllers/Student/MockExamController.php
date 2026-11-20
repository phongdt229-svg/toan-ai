<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Services\AccessControlService;
use App\Services\Learning\MockExamService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Thi thử vào 10 / THPT: danh sách đề. Làm bài + kết quả dùng chung route của Đề kiểm tra. */
class MockExamController extends Controller
{
    public function __construct(
        private readonly MockExamService $mocks,
        private readonly AccessControlService $access,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile.grade');
        $exams = $this->mocks->openFor($user);

        return view('student.mock-exams.index', [
            'exams' => $exams,
            'preferred' => $this->mocks->preferredTrack($user->studentProfile),
            'inProgress' => ExamAttempt::where('user_id', $user->id)
                ->where('status', ExamAttempt::STATUS_IN_PROGRESS)->pluck('exam_id')->flip(),
            // Đề khoá theo access_level của từng đề (chốt 19/11) — hiện ổ khoá thay vì giấu đề.
            'locked' => $exams->mapWithKeys(fn (Exam $e) => [$e->id => ! $this->access->canAccessLevel($user, $e->access_level)]),
            'mockService' => $this->mocks,
        ]);
    }
}
