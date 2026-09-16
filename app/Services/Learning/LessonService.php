<?php

namespace App\Services\Learning;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\AccessControlService;
use Illuminate\Support\Collection;

class LessonService
{
    public function __construct(private readonly AccessControlService $access) {}

    /**
     * Cây chương trình của một lớp: Subject → Chapter → Topic (kèm số bài đã xuất bản).
     */
    public function curriculumTree(Grade $grade): Collection
    {
        return Subject::query()
            ->where('grade_id', $grade->id)
            ->active()
            ->ordered()
            ->with(['chapters' => fn ($q) => $q->active()->ordered()
                ->with(['topics' => fn ($t) => $t->active()->ordered()
                    ->withCount(['lessons' => fn ($l) => $l->published()])])])
            ->get();
    }

    /**
     * Danh sách bài học của một chủ đề, kèm cờ khoá theo gói và tiến độ của user.
     *
     * @return Collection<int, Lesson>
     */
    public function lessonsForTopic(Topic $topic, ?User $user): Collection
    {
        $lessons = Lesson::query()
            ->where('topic_id', $topic->id)
            ->published()
            ->ordered()
            ->get();

        $progressByLesson = $user
            ? $user->lessonProgress()->whereIn('lesson_id', $lessons->pluck('id'))->get()->keyBy('lesson_id')
            : collect();

        return $lessons->each(function (Lesson $lesson) use ($user, $progressByLesson) {
            // Gắn tạm vào model để view dùng, không đụng tới cột DB.
            $lesson->setAttribute('is_locked', ! $this->access->canAccessLesson($user, $lesson));
            $lesson->setAttribute('user_progress', $progressByLesson->get($lesson->id));
        });
    }

    /** Bài học kèm sections, chỉ trả về nếu đã publish (hoặc người xem là tác giả/admin). */
    public function findPublishedBySlug(string $slug): ?Lesson
    {
        return Lesson::query()
            ->published()
            ->with(['sections', 'topic.chapter.subject.grade'])
            ->where('slug', $slug)
            ->first();
    }
}
