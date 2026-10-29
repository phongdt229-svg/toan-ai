<?php

namespace App\Services\Content;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonSection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

/**
 * Chương trình học công khai (/chuong-trinh) — GEO: cho công cụ AI và Google đọc được site dạy GÌ, ở LỚP nào.
 *
 * Chỉ lộ CẤU TRÚC (lớp → chương → chủ đề → bài, tóm tắt, thời lượng, tên các phần), không bao giờ lộ
 * nội dung bài: spec §6 "chưa đăng nhập bấm vào bài học → Vui lòng đăng nhập để bắt đầu học". Vì vậy
 * mọi truy vấn ở đây chọn cột tường minh và KHÔNG chọn lesson_sections.content.
 *
 * Bài chỉ hiện khi đã xuất bản và cả chuỗi lớp/môn/chương/chủ đề đang bật — cùng điều kiện học sinh thấy.
 */
class PublicCurriculumService
{
    /** Khối "đọc" được liệt kê tên ở trang tổng quan bài; khối tương tác chỉ đếm số lượng. */
    public const READING_SECTIONS = ['theory', 'example', 'insight', 'formula', 'common_mistake'];

    /**
     * Các lớp đang nhận học sinh, kèm số bài đã xuất bản (lớp chưa có bài vẫn hiện — trang đó noindex).
     *
     * @return Collection<int, Grade>
     */
    public function grades(): Collection
    {
        $counts = $this->lessonCountsByGrade();

        return Grade::active()->ordered()->get(['id', 'name', 'slug', 'level'])
            ->each(fn (Grade $grade) => $grade->setAttribute('lessons_count', $counts[$grade->id] ?? 0));
    }

    /** Nạp cây môn → chương → chủ đề → bài của một lớp (chỉ cột cần hiện). */
    public function tree(Grade $grade): Grade
    {
        abort_unless($grade->is_active && $this->inRange($grade), 404);

        return $grade->load([
            'subjects' => fn ($q) => $q->active()->ordered()->select('id', 'grade_id', 'name', 'slug', 'sort_order'),
            'subjects.chapters' => fn ($q) => $q->active()->ordered()->select('id', 'subject_id', 'name', 'slug', 'sort_order'),
            'subjects.chapters.topics' => fn ($q) => $q->active()->ordered()->select('id', 'chapter_id', 'name', 'slug', 'sort_order'),
            'subjects.chapters.topics.lessons' => fn ($q) => $q->published()->ordered()
                ->select('id', 'topic_id', 'title', 'slug', 'summary', 'sort_order', 'difficulty', 'estimated_minutes', 'access_level'),
        ]);
    }

    /**
     * Bài học công khai thuộc đúng lớp trên URL — sai lớp, chưa xuất bản hay nằm trong chương đã tắt đều 404,
     * để không ai đoán slug mà xem được bài nháp.
     */
    public function lesson(Grade $grade, string $slug): Lesson
    {
        $lesson = $this->publicLessons()
            ->where('lessons.slug', $slug)
            ->where('subjects.grade_id', $grade->id)
            ->select('lessons.id', 'lessons.topic_id', 'lessons.title', 'lessons.slug', 'lessons.summary', 'lessons.difficulty',
                'lessons.estimated_minutes', 'lessons.access_level', 'lessons.published_at', 'lessons.updated_at')
            ->first();

        abort_unless($lesson, 404);

        return $lesson->load([
            'topic:id,chapter_id,name,slug',
            'topic.chapter:id,subject_id,name',
            'topic.chapter.subject:id,grade_id,name',
            // Chỉ loại + tên phần — content là nội dung học, phải đăng nhập.
            'sections' => fn ($q) => $q->select('id', 'lesson_id', 'type', 'title', 'sort_order'),
            'topic.lessons' => fn ($q) => $q->published()->ordered()->select('id', 'topic_id', 'title', 'slug', 'sort_order'),
        ]);
    }

    /**
     * Mọi bài công khai kèm slug lớp — cho sitemap và llms.txt.
     *
     * @return \Illuminate\Support\Collection<int, object{slug: string, grade_slug: string, updated_at: string}>
     */
    public function sitemapLessons(): \Illuminate\Support\Collection
    {
        return $this->publicLessons()
            ->orderBy('grades.level')->orderBy('lessons.sort_order')->orderBy('lessons.id')
            ->toBase()
            ->get(['lessons.slug', 'lessons.title', 'lessons.summary', 'grades.slug as grade_slug', 'grades.name as grade_name', 'lessons.updated_at']);
    }

    /** "3 phần lý thuyết – ví dụ…" — tách khối đọc và khối luyện tập cho trang tổng quan. */
    public function outline(Lesson $lesson): array
    {
        [$reading, $interactive] = $lesson->sections->partition(fn (LessonSection $s) => in_array($s->type, self::READING_SECTIONS, true));

        return ['reading' => $reading->values(), 'interactive' => $interactive->countBy('type')];
    }

    /** @return array<int, int> grade_id => số bài công khai */
    private function lessonCountsByGrade(): array
    {
        return $this->publicLessons()
            ->groupBy('subjects.grade_id')
            ->toBase()
            ->selectRaw('subjects.grade_id as grade_id, COUNT(*) as lessons_count')
            ->pluck('lessons_count', 'grade_id')
            ->map(fn ($n) => (int) $n)
            ->all();
    }

    /** Bài đã xuất bản và cả chuỗi lớp → chủ đề đang bật. Join thẳng để đếm/lọc trong một truy vấn. */
    private function publicLessons(): Builder
    {
        return Lesson::query()
            ->published()
            ->join('topics', 'topics.id', '=', 'lessons.topic_id')
            ->join('chapters', 'chapters.id', '=', 'topics.chapter_id')
            ->join('subjects', 'subjects.id', '=', 'chapters.subject_id')
            ->join('grades', 'grades.id', '=', 'subjects.grade_id')
            ->where('topics.is_active', true)
            ->where('chapters.is_active', true)
            ->where('subjects.is_active', true)
            ->where('grades.is_active', true)
            ->whereBetween('grades.level', [config('learning.grade_min'), config('learning.grade_max')]);
    }

    private function inRange(Grade $grade): bool
    {
        return $grade->level >= config('learning.grade_min') && $grade->level <= config('learning.grade_max');
    }
}
