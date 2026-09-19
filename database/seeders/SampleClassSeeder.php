<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\SchoolClass;
use App\Models\User;
use App\Services\Teaching\AssignmentService;
use App\Services\Teaching\ClassService;
use Illuminate\Database\Seeder;

/**
 * Lớp 6A1 mẫu: học sinh demo đã vào lớp, có ba bài giao đủ ba loại.
 */
class SampleClassSeeder extends Seeder
{
    public function run(ClassService $classes, AssignmentService $assignments): void
    {
        $teacher = User::where('email', 'teacher@gmail.com')->first();
        $student = User::where('email', 'student@gmail.com')->first();
        $grade = Grade::where('level', 6)->first();

        if (! $teacher || ! $student || ! $grade || SchoolClass::where('name', '6A1 — Toán')->exists()) {
            return;
        }

        $class = $classes->create(['name' => '6A1 — Toán', 'grade_id' => $grade->id], $teacher);
        // Mã cố định để tài liệu hướng dẫn dùng được.
        $class->update(['code' => 'TOAN6A']);
        $classes->enroll($class, $student);

        $questionIds = Question::where('grade_id', $grade->id)
            ->published()
            ->where('type', '!=', Question::TYPE_ESSAY)
            ->pluck('id')
            ->all();

        $assignments->create($class, $teacher, [
            'title' => 'BTVN: Cộng phân số',
            'description' => '<p>Làm cẩn thận, nhớ quy đồng trước khi cộng.</p>',
            'type' => Assignment::TYPE_QUESTION_SET,
            'question_ids' => $questionIds,
            'assign_to_all' => true,
            'due_at' => now()->addDays(3)->setTime(21, 0),
            'allow_retry' => true,
            'max_attempts' => 3,
        ]);

        if ($lesson = Lesson::published()->where('slug', 'cong-hai-phan-so-khac-mau-so')->first()) {
            $assignments->create($class, $teacher, [
                'title' => 'Đọc trước: Cộng phân số khác mẫu',
                'type' => Assignment::TYPE_LESSON,
                'lesson_id' => $lesson->id,
                'assign_to_all' => true,
                'due_at' => now()->addDays(2)->setTime(20, 0),
                'allow_retry' => false,
            ]);
        }

        if ($exam = Exam::published()->where('grade_id', $grade->id)->first()) {
            $assignments->create($class, $teacher, [
                'title' => 'Kiểm tra 15 phút',
                'type' => Assignment::TYPE_EXAM,
                'exam_id' => $exam->id,
                'assign_to_all' => true,
                'due_at' => now()->addWeek()->setTime(21, 0),
                'allow_retry' => false,
            ]);
        }
    }
}
