<?php

namespace Tests\Feature\Classes;

use App\Models\Assignment;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Teaching\AssignmentService;
use App\Services\Teaching\ClassService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Database\Seeders\SampleQuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ClassroomTestCase extends TestCase
{
    use RefreshDatabase;

    protected User $teacher;
    protected Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class, GradeSeeder::class,
            SampleCurriculumSeeder::class, SampleQuestionSeeder::class,
        ]);

        $this->grade = Grade::where('level', 6)->firstOrFail();
        $this->teacher = $this->makeTeacher();
    }

    protected function makeTeacher(): User
    {
        $t = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $t->assignRole(Role::TEACHER);

        return $t;
    }

    protected function makeStudent(?string $name = null): User
    {
        $s = User::factory()->create(['status' => User::STATUS_ACTIVE, ...($name ? ['name' => $name] : [])]);
        $s->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $s->id,
            'grade_id' => $this->grade->id,
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        return $s;
    }

    protected function makeClass(?User $teacher = null): SchoolClass
    {
        return app(ClassService::class)->create(
            ['name' => 'Lớp thử '.uniqid(), 'grade_id' => $this->grade->id],
            $teacher ?? $this->teacher,
        );
    }

    protected function enroll(SchoolClass $class, User ...$students): void
    {
        foreach ($students as $s) {
            app(ClassService::class)->enroll($class, $s);
        }
    }

    /** @return array<int, int> */
    protected function autoGradableQuestionIds(): array
    {
        return Question::where('grade_id', $this->grade->id)->published()
            ->where('type', '!=', Question::TYPE_ESSAY)->orderBy('id')->pluck('id')->all();
    }

    /** @param  array<string, mixed>  $overrides */
    protected function questionSetAssignment(SchoolClass $class, array $overrides = []): Assignment
    {
        return app(AssignmentService::class)->create($class, $this->teacher, [
            'title' => 'BTVN',
            'type' => Assignment::TYPE_QUESTION_SET,
            'question_ids' => $this->autoGradableQuestionIds(),
            'assign_to_all' => true,
            'due_at' => now()->addDay(),
            'allow_retry' => false,
            ...$overrides,
        ]);
    }

    /** Đáp án đúng cho mọi câu trong bài giao. */
    protected function perfectAnswers(Assignment $assignment): array
    {
        return $assignment->questions()->with('options')->get()
            ->mapWithKeys(fn (Question $q) => [$q->id => match ($q->type) {
                Question::TYPE_SINGLE_CHOICE => $q->options->firstWhere('is_correct', true)->id,
                Question::TYPE_MULTIPLE_CHOICE => $q->options->where('is_correct', true)->pluck('id')->all(),
                Question::TYPE_TRUE_FALSE => $q->correct_answer['value'] ? '1' : '0',
                Question::TYPE_FILL_BLANK => collect($q->correct_answer['blanks'])->map(fn ($b) => $b[0])->all(),
                Question::TYPE_SHORT_ANSWER => $q->correct_answer['accepted'][0],
            }])
            ->all();
    }
}
