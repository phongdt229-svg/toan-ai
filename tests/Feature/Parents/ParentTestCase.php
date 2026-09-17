<?php

namespace Tests\Feature\Parents;

use App\Models\Grade;
use App\Models\ParentProfile;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Database\Seeders\SampleQuestionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class ParentTestCase extends TestCase
{
    use RefreshDatabase;

    protected Grade $grade;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([
            RolePermissionSeeder::class, GradeSeeder::class,
            SampleCurriculumSeeder::class, SampleQuestionSeeder::class,
        ]);

        $this->grade = Grade::where('level', 6)->firstOrFail();
    }

    protected function makeParent(): User
    {
        $p = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $p->assignRole(Role::PARENT);
        ParentProfile::create(['user_id' => $p->id, 'weekly_report_enabled' => true]);

        return $p;
    }

    protected function makeStudent(string $name = 'Nguyễn Con'): User
    {
        $s = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => $name]);
        $s->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $s->id,
            'grade_id' => $this->grade->id,
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        return $s->load('studentProfile');
    }

    protected function link(User $parent, User $student): void
    {
        app(\App\Services\Parenting\ChildLinkService::class)
            ->linkByCode($parent, $student->studentProfile()->value('link_code'));
    }
}
