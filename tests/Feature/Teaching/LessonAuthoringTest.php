<?php

namespace Tests\Feature\Teaching;

use App\Models\Lesson;
use App\Models\Role;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonAuthoringTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;
    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, SampleCurriculumSeeder::class]);

        $this->teacher = $this->makeTeacher();
        $this->topic = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();
    }

    private function makeTeacher(): User
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole(Role::TEACHER);

        return $teacher;
    }

    private function lessonPayload(array $overrides = []): array
    {
        return array_merge([
            'topic_id' => $this->topic->id,
            'title' => 'Rút gọn phân số',
            'summary' => 'Chia cả tử và mẫu cho ước chung lớn nhất.',
            'difficulty' => 'easy',
            'estimated_minutes' => 12,
            'access_level' => Lesson::ACCESS_FREE,
        ], $overrides);
    }

    public function test_teacher_creates_lesson_as_draft(): void
    {
        $this->actingAs($this->teacher)
            ->post(route('teacher.lessons.store'), $this->lessonPayload())
            ->assertRedirect();

        $lesson = Lesson::where('title', 'Rút gọn phân số')->firstOrFail();

        $this->assertSame(Lesson::STATUS_DRAFT, $lesson->status);
        $this->assertSame($this->teacher->id, $lesson->created_by);
        $this->assertSame('rut-gon-phan-so', $lesson->slug);
        $this->assertDatabaseHas('audit_logs', ['action' => 'lesson.created']);
    }

    public function test_slug_collision_gets_suffix(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.lessons.store'), $this->lessonPayload());
        $this->actingAs($this->teacher)->post(route('teacher.lessons.store'), $this->lessonPayload());

        $this->assertDatabaseHas('lessons', ['slug' => 'rut-gon-phan-so']);
        $this->assertDatabaseHas('lessons', ['slug' => 'rut-gon-phan-so-2']);
    }

    public function test_empty_lesson_cannot_be_published(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.lessons.store'), $this->lessonPayload());
        $lesson = Lesson::where('title', 'Rút gọn phân số')->firstOrFail();

        $this->actingAs($this->teacher)
            ->post(route('teacher.lessons.publish', $lesson))
            ->assertRedirect()
            ->assertSessionHas('error');

        $this->assertSame(Lesson::STATUS_DRAFT, $lesson->fresh()->status);
    }

    public function test_lesson_with_section_can_be_published_and_reaches_students(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.lessons.store'), $this->lessonPayload());
        $lesson = Lesson::where('title', 'Rút gọn phân số')->firstOrFail();

        $this->actingAs($this->teacher)->post(route('teacher.sections.store', $lesson), [
            'type' => 'theory',
            'title' => 'Quy tắc',
            'content' => '<p>Chia cả tử và mẫu cho ƯCLN.</p>',
        ]);

        $this->actingAs($this->teacher)
            ->post(route('teacher.lessons.publish', $lesson))
            ->assertRedirect();

        $lesson->refresh();
        $this->assertSame(Lesson::STATUS_PUBLISHED, $lesson->status);
        $this->assertNotNull($lesson->published_at);

        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole(Role::STUDENT);

        $this->actingAs($student)
            ->get(route('student.lesson.show', $lesson))
            ->assertOk()
            ->assertSee('Chia cả tử và mẫu cho ƯCLN.', false);
    }

    public function test_teacher_cannot_edit_another_teachers_lesson(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.lessons.store'), $this->lessonPayload());
        $lesson = Lesson::where('title', 'Rút gọn phân số')->firstOrFail();

        $other = $this->makeTeacher();

        $this->actingAs($other)->get(route('teacher.lessons.edit', $lesson))->assertForbidden();
        $this->actingAs($other)
            ->put(route('teacher.lessons.update', $lesson), $this->lessonPayload(['title' => 'Chiếm bài']))
            ->assertForbidden();
        $this->actingAs($other)->delete(route('teacher.lessons.destroy', $lesson))->assertForbidden();
    }

    public function test_teacher_only_sees_own_lessons_in_list(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.lessons.store'), $this->lessonPayload());

        $other = $this->makeTeacher();
        $this->actingAs($other)->post(route('teacher.lessons.store'), $this->lessonPayload([
            'title' => 'Bài của người khác',
        ]));

        $this->actingAs($this->teacher)
            ->get(route('teacher.lessons.index'))
            ->assertOk()
            ->assertSee('Rút gọn phân số')
            ->assertDontSee('Bài của người khác');
    }

    public function test_student_cannot_reach_authoring_pages(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole(Role::STUDENT);

        $this->actingAs($student)->get(route('teacher.lessons.index'))->assertForbidden();
        $this->actingAs($student)->get(route('teacher.lessons.create'))->assertForbidden();
    }
}
