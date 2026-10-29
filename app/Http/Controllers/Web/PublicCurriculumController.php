<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Grade;
use App\Services\Content\PublicCurriculumService;
use Illuminate\View\View;

/**
 * Chương trình học công khai (/chuong-trinh) — chỉ cấu trúc, không nội dung bài (xem PublicCurriculumService).
 * Học thật vẫn ở student.learn.* sau đăng nhập.
 */
class PublicCurriculumController extends Controller
{
    public function __construct(private readonly PublicCurriculumService $curriculum) {}

    public function index(): View
    {
        return view('public.curriculum.index', ['grades' => $this->curriculum->grades()]);
    }

    public function grade(Grade $grade): View
    {
        return view('public.curriculum.grade', ['grade' => $this->curriculum->tree($grade)]);
    }

    public function lesson(Grade $grade, string $lesson): View
    {
        $lesson = $this->curriculum->lesson($grade, $lesson);

        return view('public.curriculum.lesson', [
            'grade' => $grade,
            'lesson' => $lesson,
            'outline' => $this->curriculum->outline($lesson),
        ]);
    }
}
