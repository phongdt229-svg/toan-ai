<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\AssignmentStudent;
use App\Models\Package;
use App\Models\PlacementTest;
use App\Models\StudentLessonProgress;
use App\Services\Learning\ActivityService;
use App\Services\Learning\LearningPathService;
use App\Services\Learning\MasteryService;
use App\Services\Learning\ProgressService;
use App\Services\Learning\RecommendationService;
use App\Services\Learning\StudentReportService;
use App\Services\Learning\StudyScheduleService;
use App\Services\Learning\TopicSignalService;
use App\Services\SubscriptionService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly RecommendationService $recommendations,
        private readonly LearningPathService $paths,
        private readonly StudentReportService $reports,
        private readonly MasteryService $mastery,
        private readonly SubscriptionService $subscriptions,
        private readonly ActivityService $activity,
        private readonly StudyScheduleService $schedules,
        private readonly TopicSignalService $signals,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile.grade');
        $path = $this->paths->active($user);
        $current = $path ? $this->paths->currentSession($path) : null;

        $recent = StudentLessonProgress::query()
            ->where('user_id', $user->id)
            ->where('status', '!=', StudentLessonProgress::STATUS_COMPLETED)
            ->with('lesson.topic')
            ->latest('last_viewed_at')
            ->limit(3)
            ->get();

        return view('student.dashboard', [
            'user' => $user,
            'activityToday' => $this->activity->today($user),
            // TA-18: nhắc nhẹ khi em hay xem lời giải thay vì tự làm sau gợi ý.
            'helpNudge' => $this->signals->helpSeeking($user)['nudge'],
            'todaySlot' => $this->schedules->slotOn($user, today()),
            'hasSchedule' => $this->schedules->forStudent($user)->isNotEmpty(),

            // Gói đang dùng: học sinh gói Free thấy thẻ mời nâng cấp ngay trên trang chủ.
            'subscription' => $this->subscriptions->effective($user),
            'upgradePackage' => Package::active()->where('price', '>', 0)->orderBy('price')->first(),
            'grade' => $user->studentProfile?->grade,
            'stats' => $this->progress->summaryFor($user),
            'topicProgress' => $this->progress->progressByTopic($user, 5),
            'continueLearning' => $recent,

            // §36 Dashboard tiến độ theo lộ trình.
            'hasPlacement' => PlacementTest::where('user_id', $user->id)->exists(),
            'path' => $path,
            'currentSession' => $current,
            'currentLocked' => $current && $this->paths->isSessionLocked($current, $user),
            'sessionLimit' => $this->paths->sessionLimit($user),
            'pathService' => $this->paths,
            'averageScore' => $this->reports->averageScoreOutOf10($user),
            'weakTopics' => $this->mastery->weakTopics($user, 3),
            // "Gợi ý học hôm nay": ưu tiên buổi học của lộ trình; chưa có lộ trình thì dùng đề xuất §11.
            'recommendations' => $current ? collect() : $this->recommendations->current($user, 3),

            // Bài giao chưa làm, hạn gần nhất lên đầu — việc học sinh cần làm ngay.
            'pendingAssignments' => AssignmentStudent::query()
                ->where('student_id', $user->id)
                ->where('status', AssignmentStudent::STATUS_ASSIGNED)
                ->whereHas('assignment', fn ($q) => $q->whereNull('deleted_at')->where('status', 'published'))
                ->with('assignment.schoolClass')
                ->get()
                ->sortBy(fn ($r) => $r->assignment->due_at?->timestamp ?? PHP_INT_MAX)
                ->take(3)
                ->values(),
        ]);
    }
}
