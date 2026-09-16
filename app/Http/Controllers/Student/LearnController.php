<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Models\Topic;
use App\Services\Learning\LessonService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LearnController extends Controller
{
    public function __construct(private readonly LessonService $lessons) {}

    /** Cây chương trình của lớp học sinh đang theo (hoặc lớp được chọn). */
    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile.grade');

        $grade = $request->filled('grade')
            ? Grade::active()->where('slug', $request->string('grade'))->firstOrFail()
            : ($user->studentProfile?->grade ?? Grade::active()->ordered()->firstOrFail());

        return view('student.learn.index', [
            'grade' => $grade,
            'grades' => Grade::active()->ordered()->get(),
            'subjects' => $this->lessons->curriculumTree($grade),
        ]);
    }

    /** Danh sách bài học trong một chủ đề. */
    public function topic(Request $request, Topic $topic): View
    {
        $topic->load('chapter.subject.grade');

        return view('student.learn.topic', [
            'topic' => $topic,
            'lessons' => $this->lessons->lessonsForTopic($topic, $request->user()),
        ]);
    }
}
