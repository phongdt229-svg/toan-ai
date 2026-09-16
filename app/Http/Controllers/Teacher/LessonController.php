<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\LessonRequest;
use App\Models\Grade;
use App\Models\Lesson;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LessonController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Lesson::class);

        $lessons = Lesson::query()
            ->with('topic.chapter.subject.grade')
            // Giáo viên chỉ thấy bài của mình; admin thấy tất cả.
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('created_by', $request->user()->id))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('title', 'like', '%'.$request->string('q').'%'))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('teacher.lessons.index', ['lessons' => $lessons]);
    }

    public function create(): View
    {
        $this->authorize('create', Lesson::class);

        return view('teacher.lessons.create', [
            'lesson' => new Lesson(['difficulty' => 'medium', 'estimated_minutes' => 15]),
            'grades' => $this->gradeTree(),
        ]);
    }

    public function store(LessonRequest $request): RedirectResponse
    {
        $lesson = Lesson::create([
            ...$request->validated(),
            'slug' => $this->uniqueSlug($request->input('slug'), $request->string('title')),
            'created_by' => $request->user()->id,
            'status' => Lesson::STATUS_DRAFT,
        ]);

        $this->audit->log('lesson.created', $lesson, null, ['title' => $lesson->title]);

        return redirect()
            ->route('teacher.lessons.edit', $lesson)
            ->with('status', 'Đã tạo bài học. Thêm nội dung bên dưới.');
    }

    public function edit(Lesson $lesson): View
    {
        $this->authorize('update', $lesson);

        return view('teacher.lessons.edit', [
            'lesson' => $lesson->load('sections', 'topic.chapter.subject.grade'),
            'grades' => $this->gradeTree(),
        ]);
    }

    public function update(LessonRequest $request, Lesson $lesson): RedirectResponse
    {
        $old = $lesson->only(['title', 'access_level', 'topic_id']);

        $lesson->update([
            ...$request->validated(),
            'slug' => $this->uniqueSlug($request->input('slug'), $request->string('title'), $lesson),
        ]);

        $this->audit->log('lesson.updated', $lesson, $old, $lesson->only(['title', 'access_level', 'topic_id']));

        return back()->with('status', 'Đã lưu thông tin bài học.');
    }

    /**
     * Xuất bản / gỡ xuất bản. Bài không có section thì không cho publish —
     * học sinh mở ra sẽ thấy trang trống.
     */
    public function togglePublish(Lesson $lesson): RedirectResponse
    {
        $this->authorize('publish', $lesson);

        if (! $lesson->isPublished() && $lesson->sections()->count() === 0) {
            return back()->with('error', 'Bài học chưa có nội dung nào, không thể xuất bản.');
        }

        $wasPublished = $lesson->isPublished();

        $lesson->update([
            'status' => $wasPublished ? Lesson::STATUS_DRAFT : Lesson::STATUS_PUBLISHED,
            'published_at' => $wasPublished ? null : now(),
        ]);

        $this->audit->log(
            $wasPublished ? 'lesson.unpublished' : 'lesson.published',
            $lesson,
            ['status' => $wasPublished ? Lesson::STATUS_PUBLISHED : Lesson::STATUS_DRAFT],
            ['status' => $lesson->status],
        );

        return back()->with('status', $wasPublished ? 'Đã gỡ xuất bản.' : 'Đã xuất bản bài học.');
    }

    public function destroy(Lesson $lesson): RedirectResponse
    {
        $this->authorize('delete', $lesson);

        $this->audit->log('lesson.deleted', $lesson, ['title' => $lesson->title], null);
        $lesson->delete();

        return redirect()
            ->route('teacher.lessons.index')
            ->with('status', 'Đã xoá bài học.');
    }

    /** Cây lớp → môn → chương → chủ đề để chọn chỗ đặt bài học. */
    private function gradeTree()
    {
        return Grade::query()
            ->active()
            ->ordered()
            ->with(['subjects' => fn ($s) => $s->active()->ordered()
                ->with(['chapters' => fn ($c) => $c->active()->ordered()
                    ->with(['topics' => fn ($t) => $t->active()->ordered()])])])
            ->get();
    }

    private function uniqueSlug(?string $given, string $title, ?Lesson $ignore = null): string
    {
        $base = Str::slug($given ?: $title) ?: 'bai-hoc';
        $slug = $base;
        $i = 2;

        while (Lesson::withTrashed()
            ->where('slug', $slug)
            ->when($ignore, fn ($q) => $q->whereKeyNot($ignore->id))
            ->exists()
        ) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
