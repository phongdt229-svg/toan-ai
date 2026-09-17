<?php

namespace Tests\Feature\Placement;

use App\Models\Grade;
use App\Models\PlacementTest;
use App\Models\Question;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AI\Providers\FakeProvider;
use App\Services\Learning\PlacementTestService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Database\Seeders\SamplePathSeeder;
use Database\Seeders\SampleQuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Dữ liệu mẫu: Lớp 6, 2 chủ đề ("Phép cộng phân số", "So sánh phân số"),
 * 12 câu tự chấm — Dễ 4 · TB 5 · Khó 3.
 */
abstract class PlacementTestCase extends TestCase
{
    use RefreshDatabase;

    protected Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.provider' => 'fake']);

        $this->seed([
            RolePermissionSeeder::class, GradeSeeder::class,
            SampleCurriculumSeeder::class, SampleQuestionSeeder::class, SamplePathSeeder::class,
        ]);

        $this->grade = Grade::where('level', 6)->firstOrFail();
    }

    protected function fake(): FakeProvider
    {
        return app(FakeProvider::class);
    }

    protected function service(): PlacementTestService
    {
        return app(PlacementTestService::class);
    }

    /** @param  array<string, mixed>  $profile */
    protected function makeStudent(array $profile = []): User
    {
        $s = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $s->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $s->id,
            'grade_id' => $this->grade->id,
            'link_code' => StudentProfile::generateLinkCode(),
            'self_assessed_level' => 'good',
            ...$profile,
        ]);

        return $s;
    }

    /** Đáp án đúng cho từng câu trong bài đầu vào (theo bản chụp). */
    protected function perfectAnswers(PlacementTest $test): array
    {
        return $test->questions()->get()->mapWithKeys(function ($pq) {
            $q = $pq->toQuestion();

            return [$pq->id => match ($q->type) {
                Question::TYPE_SINGLE_CHOICE => (string) $q->options->firstWhere('is_correct', true)->id,
                Question::TYPE_MULTIPLE_CHOICE => $q->options->where('is_correct', true)->pluck('id')->map(fn ($v) => (string) $v)->values()->all(),
                Question::TYPE_TRUE_FALSE => $q->correct_answer['value'] ? '1' : '0',
                Question::TYPE_FILL_BLANK => collect($q->correct_answer['blanks'])->map(fn ($b) => $b[0])->all(),
                Question::TYPE_SHORT_ANSWER => $q->correct_answer['accepted'][0],
            }];
        })->all();
    }

    /** Học sinh làm đầu vào (trắng hoặc đúng hết) → có kết quả và lộ trình. */
    protected function completePlacement(User $student, bool $perfect = false): PlacementTest
    {
        $test = $this->service()->start($student);

        return $this->service()->submit($test, $perfect ? $this->perfectAnswers($test) : []);
    }
}
