<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\LessonSectionRequest;
use App\Models\Lesson;
use App\Models\LessonSection;
use Illuminate\Http\RedirectResponse;

class LessonSectionController extends Controller
{
    public function store(LessonSectionRequest $request, Lesson $lesson): RedirectResponse
    {
        $lesson->sections()->create([
            ...$request->validated(),
            'sort_order' => $request->integer('sort_order') ?: ($lesson->sections()->max('sort_order') + 1),
        ]);

        return back()->with('status', 'Đã thêm phần nội dung.');
    }

    public function update(LessonSectionRequest $request, Lesson $lesson, LessonSection $section): RedirectResponse
    {
        abort_unless($section->lesson_id === $lesson->id, 404);

        $section->update($request->validated());

        return back()->with('status', 'Đã cập nhật phần nội dung.');
    }

    public function destroy(Lesson $lesson, LessonSection $section): RedirectResponse
    {
        $this->authorize('update', $lesson);
        abort_unless($section->lesson_id === $lesson->id, 404);

        $section->delete();

        return back()->with('status', 'Đã xoá phần nội dung.');
    }
}
