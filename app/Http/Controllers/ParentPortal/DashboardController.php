<?php

namespace App\Http\Controllers\ParentPortal;

use App\Http\Controllers\Controller;
use App\Services\Learning\StudentReportService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly StudentReportService $reports,
        private readonly SubscriptionService $subscriptions,
    ) {}

    /** §14: mỗi con một thẻ tóm tắt — bấm vào để xem báo cáo đầy đủ. */
    public function index(Request $request): View
    {
        $children = $request->user()
            ->linkedChildren()
            ->with('studentProfile.grade')
            ->orderBy('name')
            ->get();

        return view('parent.dashboard', [
            'children' => $children->map(fn ($child) => [
                'student' => $child,
                'curriculum_percent' => $this->reports->curriculumPercent($child),
                'average_score' => $this->reports->averageScoreOutOf10($child),
                'study_seconds' => $this->reports->studySeconds($child),
                'assignments' => $this->reports->pendingAssignments($child),
                // Con đang dùng gói nào — để phụ huynh mua / gia hạn ngay từ đây.
                'subscription' => $this->subscriptions->effective($child),
            ]),
        ]);
    }
}
