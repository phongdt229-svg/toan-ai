<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\TrackProgressRequest;
use App\Models\Lesson;
use App\Services\AccessControlService;
use App\Services\Learning\ProgressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function __construct(
        private readonly ProgressService $progress,
        private readonly AccessControlService $access,
    ) {}

    public function show(Request $request, Lesson $lesson): View
    {
        abort_unless($lesson->isPublished(), 404);

        $lesson->load('sections', 'topic.chapter.subject.grade');
        $user = $request->user();

        // Ngoài gói → trả trang paywall kèm phần lý thuyết đầu làm preview (§7 plan).
        if (! $this->access->canAccessLesson($user, $lesson)) {
            return view('student.lesson.locked', [
                'lesson' => $lesson,
                'requiredTier' => $lesson->access_level,
                'preview' => $lesson->sections->firstWhere('type', 'theory'),
            ]);
        }

        $progress = $this->progress->startOrTouch($user, $lesson);

        return view('student.lesson.show', [
            'lesson' => $lesson,
            'progress' => $progress,
            'completedSections' => collect($progress->sections_completed ?? []),
        ]);
    }

    /** Ping từ client khi học sinh xem xong một section. */
    public function trackProgress(TrackProgressRequest $request, Lesson $lesson): JsonResponse
    {
        abort_unless($lesson->isPublished(), 404);
        abort_unless($this->access->canAccessLesson($request->user(), $lesson), 402);

        $progress = $this->progress->markSectionCompleted(
            $request->user(),
            $lesson,
            $request->integer('section_id'),
            $request->integer('seconds_spent'),
        );

        return response()->json([
            'success' => true,
            'data' => [
                'progress_percent' => $progress->progress_percent,
                'status' => $progress->status,
            ],
        ]);
    }

    public function complete(Request $request, Lesson $lesson): RedirectResponse
    {
        abort_unless($lesson->isPublished(), 404);
        abort_unless($this->access->canAccessLesson($request->user(), $lesson), 402);

        $this->progress->completeLesson($request->user(), $lesson);

        return redirect()
            ->route('student.learn.topic', $lesson->topic_id)
            ->with('status', "Đã hoàn thành bài: {$lesson->title}");
    }
}
