<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\Question;
use App\Models\User;
use App\Services\Teaching\ExamBuilderService;
use Illuminate\Database\Seeder;

/**
 * Một đề mẫu đã xuất bản từ câu hỏi mẫu, để demo luồng làm bài.
 */
class SampleExamSeeder extends Seeder
{
    public function run(ExamBuilderService $builder): void
    {
        $teacher = User::where('email', 'teacher@toan-ai.local')->first();
        $grade = Grade::where('level', 6)->first();

        if (! $teacher || ! $grade) {
            return;
        }

        $exam = Exam::withTrashed()->where('slug', 'kiem-tra-15-phut-phep-cong-phan-so')->first();

        if (! $exam) {
            $exam = Exam::create([
                'title' => 'Kiểm tra 15 phút: Phép cộng phân số',
                'slug' => 'kiem-tra-15-phut-phep-cong-phan-so',
                'description' => '<p>Gồm trắc nghiệm, điền chỗ trống và một câu tự luận.</p>',
                'grade_id' => $grade->id,
                'type' => 'quiz',
                'duration_minutes' => 15,
                'difficulty' => 'mixed',
                'access_level' => 'free',
                'max_attempts' => 3,
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'show_answers_after_submit' => true,
                'status' => Exam::STATUS_DRAFT,
                'created_by' => $teacher->id,
            ]);
        }

        if (! $exam->hasAttempts()) {
            $exam->questions()->detach();
            $builder->addQuestions(
                $exam,
                Question::where('grade_id', $grade->id)->published()->pluck('id')->all(),
                $teacher,
            );
        }

        $exam->update(['status' => Exam::STATUS_PUBLISHED]);
    }
}
