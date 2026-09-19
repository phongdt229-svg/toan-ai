<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Lesson;
use App\Models\Question;
use App\Services\Teaching\StudentInsightService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Một ô tìm xuyên suốt bài học + câu hỏi + học sinh — trước đây mỗi trang chỉ lọc riêng (§13). */
class SearchController extends Controller
{
    public function __construct(private readonly StudentInsightService $insights) {}

    public function index(Request $request): View
    {
        $q = trim($request->string('q')->toString());
        $teacher = $request->user();

        $lessons = collect();
        $questions = collect();
        $students = collect();

        // Dưới 2 ký tự thì quét toàn bảng cũng không có ý nghĩa — chỉ tốn query.
        if (mb_strlen($q) >= 2) {
            $lessons = Lesson::query()
                ->where('created_by', $teacher->id)
                ->where('title', 'like', "%{$q}%")
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'title', 'slug', 'status']);

            $questions = Question::query()
                ->where('created_by', $teacher->id)
                ->where('content', 'like', "%{$q}%")
                ->orderByDesc('id')
                ->limit(10)
                ->get(['id', 'content', 'type', 'status']);

            // studentsFor() trả về mảng thống kê {student, classes, assigned, ...} chứ không phải User trực tiếp.
            $needle = mb_strtolower($q);
            $students = $this->insights->studentsFor($teacher)
                ->pluck('student')
                ->filter(fn ($s) => str_contains(mb_strtolower($s->name), $needle)
                    || str_contains(mb_strtolower($s->email), $needle))
                ->take(10)
                ->values();
        }

        return view('teacher.search', [
            'q' => $q,
            'lessons' => $lessons,
            'questions' => $questions,
            'students' => $students,
        ]);
    }
}
