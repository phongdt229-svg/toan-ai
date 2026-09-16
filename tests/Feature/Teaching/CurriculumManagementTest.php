<?php

namespace Tests\Feature\Teaching;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Role;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurriculumManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, SampleCurriculumSeeder::class]);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole(Role::ADMIN);
    }

    public function test_admin_builds_full_curriculum_branch(): void
    {
        $grade = Grade::where('level', 7)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.curriculum.subjects.store', $grade), ['name' => 'Toán'])
            ->assertRedirect();

        $subject = Subject::where('grade_id', $grade->id)->firstOrFail();
        $this->assertSame('toan', $subject->slug);

        $this->actingAs($this->admin)
            ->post(route('admin.curriculum.chapters.store', $subject), ['name' => 'Số hữu tỉ']);

        $chapter = Chapter::where('subject_id', $subject->id)->firstOrFail();

        $this->actingAs($this->admin)
            ->post(route('admin.curriculum.topics.store', $chapter), ['name' => 'Cộng trừ số hữu tỉ']);

        $this->assertDatabaseHas('topics', [
            'chapter_id' => $chapter->id,
            'slug' => 'cong-tru-so-huu-ti',
        ]);
    }

    public function test_topic_with_lessons_cannot_be_deleted(): void
    {
        $topic = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.curriculum.topics.destroy', $topic))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertDatabaseHas('topics', ['id' => $topic->id]);
    }

    public function test_empty_topic_can_be_deleted(): void
    {
        $topic = Topic::where('slug', 'so-sanh-phan-so')->firstOrFail();

        $this->actingAs($this->admin)
            ->delete(route('admin.curriculum.topics.destroy', $topic))
            ->assertRedirect()
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('topics', ['id' => $topic->id]);
    }

    public function test_teacher_cannot_manage_curriculum(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole(Role::TEACHER);

        $this->actingAs($teacher)
            ->get(route('admin.curriculum.index'))
            ->assertForbidden();
    }
}
