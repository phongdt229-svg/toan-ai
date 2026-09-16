<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Quản lý cây Grade → Subject → Chapter → Topic (§7).
 * Lesson do giáo viên soạn ở Teacher portal, không nằm ở đây.
 */
class CurriculumController extends Controller
{
    public function index(Request $request): View
    {
        $grade = $request->filled('grade')
            ? Grade::where('slug', $request->string('grade'))->firstOrFail()
            : Grade::active()->ordered()->firstOrFail();

        return view('admin.curriculum.index', [
            'grade' => $grade,
            'grades' => Grade::active()->ordered()->get(),
            'subjects' => Subject::where('grade_id', $grade->id)
                ->ordered()
                ->with(['chapters' => fn ($c) => $c->ordered()
                    ->with(['topics' => fn ($t) => $t->ordered()->withCount('lessons')])])
                ->get(),
        ]);
    }

    public function storeSubject(Request $request, Grade $grade): RedirectResponse
    {
        $data = $this->validateNode($request);

        Subject::create([
            'grade_id' => $grade->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(Subject::class, $data['name'], ['grade_id' => $grade->id]),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('status', "Đã thêm môn học vào {$grade->name}.");
    }

    public function storeChapter(Request $request, Subject $subject): RedirectResponse
    {
        $data = $this->validateNode($request);

        Chapter::create([
            'subject_id' => $subject->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(Chapter::class, $data['name'], ['subject_id' => $subject->id]),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('status', 'Đã thêm chương.');
    }

    public function storeTopic(Request $request, Chapter $chapter): RedirectResponse
    {
        $data = $this->validateNode($request);

        Topic::create([
            'chapter_id' => $chapter->id,
            'name' => $data['name'],
            'slug' => $this->uniqueSlug(Topic::class, $data['name'], ['chapter_id' => $chapter->id]),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        return back()->with('status', 'Đã thêm chủ đề.');
    }

    public function destroyTopic(Topic $topic): RedirectResponse
    {
        // Xoá chủ đề sẽ cascade xoá bài học — chặn lại nếu còn nội dung.
        if ($topic->lessons()->exists()) {
            return back()->with('error', 'Chủ đề còn bài học, hãy chuyển hoặc xoá bài học trước.');
        }

        $topic->delete();

        return back()->with('status', 'Đã xoá chủ đề.');
    }

    /** @return array{name: string, sort_order: ?int} */
    private function validateNode(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:191'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:999'],
        ], [], ['name' => 'tên', 'sort_order' => 'thứ tự']);
    }

    /**
     * @param  class-string<\Illuminate\Database\Eloquent\Model>  $model
     * @param  array<string, mixed>  $scope
     */
    private function uniqueSlug(string $model, string $name, array $scope): string
    {
        $base = Str::slug($name) ?: 'muc';
        $slug = $base;
        $i = 2;

        while ($model::where($scope)->where('slug', $slug)->exists()) {
            $slug = "{$base}-{$i}";
            $i++;
        }

        return $slug;
    }
}
