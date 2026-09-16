<?php

namespace App\Services\Learning;

use App\Models\Lesson;
use App\Models\StudentLessonProgress;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class ProgressService
{
    /** Chặn gian lận thời gian: một lần ping không thể cộng quá 5 phút. */
    private const MAX_SECONDS_PER_PING = 300;

    public function startOrTouch(User $user, Lesson $lesson): StudentLessonProgress
    {
        return StudentLessonProgress::updateOrCreate(
            ['user_id' => $user->id, 'lesson_id' => $lesson->id],
            ['last_viewed_at' => now()],
        );
    }

    /**
     * Đánh dấu một section đã xem xong và cộng thời gian học.
     * progress_percent tính lại từ số section thực tế của bài, không tin số client gửi.
     */
    public function markSectionCompleted(
        User $user,
        Lesson $lesson,
        int $sectionId,
        int $secondsSpent = 0,
    ): StudentLessonProgress {
        $seconds = max(0, min($secondsSpent, self::MAX_SECONDS_PER_PING));

        return DB::transaction(function () use ($user, $lesson, $sectionId, $seconds) {
            $progress = StudentLessonProgress::query()
                ->where('user_id', $user->id)
                ->where('lesson_id', $lesson->id)
                ->lockForUpdate()
                ->first()
                ?? new StudentLessonProgress(['user_id' => $user->id, 'lesson_id' => $lesson->id]);

            $completed = collect($progress->sections_completed ?? [])
                ->push($sectionId)
                ->unique()
                ->values();

            // Chỉ giữ id thuộc về bài này — tránh client gửi id rác làm phồng tiến độ.
            $validIds = $lesson->sections()->pluck('id');
            $completed = $completed->intersect($validIds)->values();

            $total = $validIds->count();

            $progress->sections_completed = $completed->all();
            $progress->progress_percent = $total > 0 ? (int) round($completed->count() / $total * 100) : 0;
            $progress->time_spent_seconds += $seconds;
            $progress->last_viewed_at = now();

            if ($progress->progress_percent >= 100) {
                $progress->status = StudentLessonProgress::STATUS_COMPLETED;
                $progress->completed_at ??= now();
            } else {
                $progress->status = StudentLessonProgress::STATUS_IN_PROGRESS;
            }

            $progress->save();

            return $progress;
        });
    }

    /** Học sinh bấm "Hoàn thành bài học" — đánh dấu toàn bộ section. */
    public function completeLesson(User $user, Lesson $lesson): StudentLessonProgress
    {
        $sectionIds = $lesson->sections()->pluck('id')->all();

        return DB::transaction(function () use ($user, $lesson, $sectionIds) {
            $progress = StudentLessonProgress::updateOrCreate(
                ['user_id' => $user->id, 'lesson_id' => $lesson->id],
                [
                    'sections_completed' => $sectionIds,
                    'progress_percent' => 100,
                    'status' => StudentLessonProgress::STATUS_COMPLETED,
                    'last_viewed_at' => now(),
                ],
            );

            $progress->completed_at ??= now();
            $progress->save();

            return $progress;
        });
    }

    /** Số liệu cho dashboard học sinh (§9). */
    public function summaryFor(User $user): array
    {
        $rows = StudentLessonProgress::query()
            ->where('user_id', $user->id)
            ->selectRaw('COUNT(*) as total')
            ->selectRaw('SUM(status = ?) as completed', [StudentLessonProgress::STATUS_COMPLETED])
            ->selectRaw('COALESCE(SUM(time_spent_seconds), 0) as seconds')
            ->first();

        return [
            'lessons_started' => (int) ($rows->total ?? 0),
            'lessons_completed' => (int) ($rows->completed ?? 0),
            'study_minutes' => (int) round(((int) ($rows->seconds ?? 0)) / 60),
        ];
    }

    /**
     * Tiến độ theo chủ đề — bảng "Số học 91% / Đại số 85%..." ở §9.
     *
     * @return array<int, array{topic: string, percent: int}>
     */
    public function progressByTopic(User $user, int $limit = 10): array
    {
        return StudentLessonProgress::query()
            ->join('lessons', 'lessons.id', '=', 'student_lesson_progress.lesson_id')
            ->join('topics', 'topics.id', '=', 'lessons.topic_id')
            ->where('student_lesson_progress.user_id', $user->id)
            ->groupBy('topics.id', 'topics.name')
            ->orderByDesc('percent')
            ->limit($limit)
            ->selectRaw('topics.name as topic, ROUND(AVG(student_lesson_progress.progress_percent)) as percent')
            ->get()
            ->map(fn ($row) => ['topic' => $row->topic, 'percent' => (int) $row->percent])
            ->all();
    }
}
