<?php

namespace Tests\Feature\Learning;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, SampleCurriculumSeeder::class]);

        $this->student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->student->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $this->student->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => StudentProfile::generateLinkCode(),
        ]);
    }

    public function test_free_student_hits_paywall_on_pro_lesson(): void
    {
        $lesson = Lesson::where('slug', 'cong-hai-phan-so-khac-mau-so')->firstOrFail();
        $lesson->update(['access_level' => Lesson::ACCESS_PRO]);

        $this->actingAs($this->student)
            ->get(route('student.lesson.show', $lesson))
            ->assertOk()
            ->assertSee('thuộc gói PRO')
            ->assertDontSee('Hoàn thành bài học');
    }

    public function test_locked_lesson_rejects_progress_tracking(): void
    {
        $lesson = Lesson::where('slug', 'cong-hai-phan-so-khac-mau-so')->firstOrFail();
        $lesson->update(['access_level' => Lesson::ACCESS_PREMIUM]);

        $this->actingAs($this->student)
            ->postJson(route('student.lesson.progress', $lesson), [
                'section_id' => $lesson->sections()->value('id'),
            ])
            ->assertStatus(402);
    }

    public function test_teacher_can_read_premium_content_for_review(): void
    {
        $lesson = Lesson::where('slug', 'cong-hai-phan-so-khac-mau-so')->firstOrFail();
        $lesson->update(['access_level' => Lesson::ACCESS_PREMIUM]);

        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole(Role::TEACHER);

        $this->assertTrue(
            app(\App\Services\AccessControlService::class)->canAccessLesson($teacher, $lesson),
        );
    }
}
