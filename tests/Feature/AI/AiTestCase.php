<?php

namespace Tests\Feature\AI;

use App\Models\Grade;
use App\Models\Question;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\AI\Providers\FakeProvider;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Database\Seeders\SampleQuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class AiTestCase extends TestCase
{
    use RefreshDatabase;

    protected Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.provider' => 'fake']);

        $this->seed([
            RolePermissionSeeder::class, GradeSeeder::class,
            SampleCurriculumSeeder::class, SampleQuestionSeeder::class,
        ]);

        $this->grade = Grade::where('level', 6)->firstOrFail();
    }

    protected function fake(): FakeProvider
    {
        return app(FakeProvider::class);
    }

    /** @param  array<string, mixed>  $profile */
    protected function makeStudent(array $profile = []): User
    {
        $s = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Minh An']);
        $s->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $s->id,
            'grade_id' => $this->grade->id,
            'link_code' => StudentProfile::generateLinkCode(),
            ...$profile,
        ]);

        return $s;
    }

    protected function makeTeacher(): User
    {
        $t = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $t->assignRole(Role::TEACHER);

        return $t;
    }

    protected function question(string $type = Question::TYPE_SINGLE_CHOICE): Question
    {
        return Question::where('type', $type)->published()->with('options')->firstOrFail();
    }

    /** Toàn bộ nội dung prompt của lượt gọi gần nhất, để kiểm tra điều gì đã gửi lên AI. */
    protected function lastPrompt(): string
    {
        return collect($this->fake()->lastRequest()?->messages ?? [])->pluck('content')->implode("\n---\n");
    }
}
