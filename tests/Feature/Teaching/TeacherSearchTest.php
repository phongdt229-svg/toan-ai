<?php

namespace Tests\Feature\Teaching;

use App\Models\Grade;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Topic;
use App\Models\User;
use App\Services\Teaching\ClassService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Ô tìm kiếm chung bài học + câu hỏi + học sinh cho giáo viên (§13). */
class TeacherSearchTest extends TestCase
{
    use RefreshDatabase;

    private User $teacher;

    private Grade $grade;

    private Topic $topic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, SampleCurriculumSeeder::class]);

        $this->teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->teacher->assignRole(Role::TEACHER);
        $this->grade = Grade::where('level', 6)->firstOrFail();
        $this->topic = Topic::where('slug', 'phep-cong-phan-so')->firstOrFail();
    }

    private function makeStudent(string $name): User
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => $name]);
        $student->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $student->id, 'grade_id' => $this->grade->id,
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        return $student;
    }

    public function test_search_finds_own_lesson_question_and_student(): void
    {
        $this->actingAs($this->teacher)->post(route('teacher.lessons.store'), [
            'topic_id' => $this->topic->id, 'title' => 'Rút gọn phân số nâng cao',
            'summary' => 'Bài nâng cao', 'difficulty' => 'easy', 'estimated_minutes' => 10,
            'access_level' => 'free',
        ]);

        $this->actingAs($this->teacher)->post(route('teacher.questions.store'), [
            'grade_id' => $this->grade->id, 'topic_id' => $this->topic->id,
            'content' => '<p>Rút gọn phân số 8/12 bằng bao nhiêu?</p>', 'difficulty' => 'easy', 'points' => 1,
            'status' => 'published', 'type' => 'single_choice',
            'options' => [['content' => '2/3'], ['content' => '1/2']],
            'correct_options' => ['0'],
        ]);

        $class = app(ClassService::class)->create(['name' => 'Lớp thử', 'grade_id' => $this->grade->id], $this->teacher);
        $student = $this->makeStudent('Nguyễn Rút Gọn');
        app(ClassService::class)->enroll($class, $student);

        $response = $this->actingAs($this->teacher)
            ->get(route('teacher.search', ['q' => 'Rút gọn']))
            ->assertOk();

        $response->assertSee('Rút gọn phân số nâng cao');
        $response->assertSee('Rút gọn phân số 8/12');
        $response->assertSee('Nguyễn Rút Gọn');
    }

    public function test_search_does_not_leak_another_teachers_content(): void
    {
        $other = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $other->assignRole(Role::TEACHER);

        $this->actingAs($other)->post(route('teacher.lessons.store'), [
            'topic_id' => $this->topic->id, 'title' => 'Bí mật của giáo viên khác',
            'summary' => 'x', 'difficulty' => 'easy', 'estimated_minutes' => 10, 'access_level' => 'free',
        ]);

        $this->actingAs($this->teacher)
            ->get(route('teacher.search', ['q' => 'Bí mật']))
            ->assertOk()
            ->assertDontSee('Bí mật của giáo viên khác');
    }

    public function test_short_query_shows_guidance_not_results(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.search', ['q' => 'a']))
            ->assertOk()
            ->assertSee('ít nhất 2 ký tự');
    }

    public function test_no_match_shows_empty_state(): void
    {
        $this->actingAs($this->teacher)
            ->get(route('teacher.search', ['q' => 'khongtontai12345']))
            ->assertOk()
            ->assertSee('Không tìm thấy kết quả');
    }

    public function test_student_cannot_reach_teacher_search(): void
    {
        $student = $this->makeStudent('Học sinh');

        $this->actingAs($student)->get(route('teacher.search', ['q' => 'a']))->assertForbidden();
    }
}
