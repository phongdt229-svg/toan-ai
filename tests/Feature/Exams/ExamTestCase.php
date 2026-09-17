<?php

namespace Tests\Feature\Exams;

use App\Models\Exam;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Database\Seeders\SampleQuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ExamTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected User $student;
    protected Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class, GradeSeeder::class,
            SampleCurriculumSeeder::class, SampleQuestionSeeder::class,
        ]);

        $this->grade = Grade::where('level', 6)->firstOrFail();

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole(Role::TEACHER);

        $this->student = $this->makeStudent();
    }

    protected function makeStudent(): User
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $student->id,
            'grade_id' => $this->grade->id,
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        return $student;
    }

    /**
     * Đề đã xuất bản, gắn sẵn toàn bộ câu hỏi mẫu (7 câu, 12 điểm, có 1 tự luận).
     *
     * @param  array<string, mixed>  $attrs
     */
    protected function publishedExam(array $attrs = []): Exam
    {
        $exam = Exam::create([
            'title' => 'Đề thử',
            'slug' => 'de-thu-'.uniqid(),
            'grade_id' => $this->grade->id,
            'type' => 'test',
            'duration_minutes' => 30,
            'difficulty' => 'mixed',
            'access_level' => 'free',
            'max_attempts' => 2,
            'shuffle_questions' => false,
            'shuffle_options' => false,
            'show_answers_after_submit' => true,
            'status' => Exam::STATUS_PUBLISHED,
            'created_by' => $this->teacher->id,
            ...$attrs,
        ]);

        $questions = Question::where('grade_id', $this->grade->id)->published()->orderBy('id')->get();

        foreach ($questions as $i => $q) {
            $exam->questions()->attach($q->id, ['sort_order' => $i + 1, 'points' => $q->points]);
        }

        $exam->update([
            'total_questions' => $questions->count(),
            'total_points' => $questions->sum('points'),
        ]);

        return $exam->refresh();
    }

    /** Đáp án đúng cho một câu, theo đúng định dạng client gửi lên. */
    protected function correctValue(Question $q): mixed
    {
        $q->loadMissing('options');

        return match ($q->type) {
            Question::TYPE_SINGLE_CHOICE => (string) $q->options->firstWhere('is_correct', true)->id,
            Question::TYPE_MULTIPLE_CHOICE => $q->options->where('is_correct', true)->pluck('id')->map(fn ($v) => (string) $v)->values()->all(),
            Question::TYPE_TRUE_FALSE => $q->correct_answer['value'] ? '1' : '0',
            Question::TYPE_FILL_BLANK => collect($q->correct_answer['blanks'])->map(fn ($b) => $b[0])->all(),
            Question::TYPE_SHORT_ANSWER => $q->correct_answer['accepted'][0],
            Question::TYPE_ESSAY => 'Bước 1: tìm mẫu chung. Bước 2: quy đồng. Bước 3: cộng tử.',
        };
    }
}
