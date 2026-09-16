<?php

namespace Tests\Feature\Learning;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Role;
use App\Models\StudentLessonProgress;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Database\Seeders\SampleCurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LessonProgressTest extends TestCase
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

    private function sampleLesson(): Lesson
    {
        return Lesson::where('slug', 'cong-hai-phan-so-khac-mau-so')->firstOrFail();
    }

    public function test_student_sees_curriculum_tree_and_topic_lessons(): void
    {
        $this->actingAs($this->student)
            ->get(route('student.learn.index'))
            ->assertOk()
            ->assertSee('Phân số')
            ->assertSee('Phép cộng phân số');

        $topic = $this->sampleLesson()->topic;

        $this->actingAs($this->student)
            ->get(route('student.learn.topic', $topic))
            ->assertOk()
            ->assertSee('Cộng hai phân số khác mẫu số');
    }

    public function test_opening_a_lesson_creates_progress_record(): void
    {
        $lesson = $this->sampleLesson();

        $this->actingAs($this->student)
            ->get(route('student.lesson.show', $lesson))
            ->assertOk()
            ->assertSee('Quy tắc');

        $this->assertDatabaseHas('student_lesson_progress', [
            'user_id' => $this->student->id,
            'lesson_id' => $lesson->id,
        ]);
    }

    public function test_marking_sections_raises_progress_percent(): void
    {
        $lesson = $this->sampleLesson();
        $sections = $lesson->sections()->pluck('id');

        $this->actingAs($this->student)->get(route('student.lesson.show', $lesson));

        $this->actingAs($this->student)
            ->postJson(route('student.lesson.progress', $lesson), [
                'section_id' => $sections->first(),
                'seconds_spent' => 60,
            ])
            ->assertOk()
            ->assertJsonPath('success', true);

        $progress = StudentLessonProgress::where('user_id', $this->student->id)
            ->where('lesson_id', $lesson->id)
            ->firstOrFail();

        $expected = (int) round(1 / $sections->count() * 100);
        $this->assertSame($expected, $progress->progress_percent);
        $this->assertSame(60, $progress->time_spent_seconds);
        $this->assertSame(StudentLessonProgress::STATUS_IN_PROGRESS, $progress->status);
    }

    public function test_section_from_another_lesson_is_rejected(): void
    {
        $lesson = $this->sampleLesson();
        $otherSection = Lesson::where('slug', 'cong-hai-phan-so-cung-mau-so')
            ->firstOrFail()->sections()->value('id');

        $this->actingAs($this->student)
            ->postJson(route('student.lesson.progress', $lesson), ['section_id' => $otherSection])
            ->assertStatus(422)
            ->assertJsonValidationErrors('section_id');
    }

    public function test_time_spent_is_capped_per_ping(): void
    {
        $lesson = $this->sampleLesson();

        // 9999 giây cho một section là bịa — Form Request chặn ở max:300.
        $this->actingAs($this->student)
            ->postJson(route('student.lesson.progress', $lesson), [
                'section_id' => $lesson->sections()->value('id'),
                'seconds_spent' => 9999,
            ])
            ->assertStatus(422)
            ->assertJsonValidationErrors('seconds_spent');
    }

    public function test_completing_lesson_marks_all_sections(): void
    {
        $lesson = $this->sampleLesson();

        $this->actingAs($this->student)
            ->post(route('student.lesson.complete', $lesson))
            ->assertRedirect(route('student.learn.topic', $lesson->topic_id));

        $progress = StudentLessonProgress::where('user_id', $this->student->id)
            ->where('lesson_id', $lesson->id)
            ->firstOrFail();

        $this->assertSame(100, $progress->progress_percent);
        $this->assertSame(StudentLessonProgress::STATUS_COMPLETED, $progress->status);
        $this->assertNotNull($progress->completed_at);
        $this->assertCount($lesson->sections()->count(), $progress->sections_completed);
    }

    public function test_dashboard_shows_real_progress_numbers(): void
    {
        $lesson = $this->sampleLesson();
        $this->actingAs($this->student)->post(route('student.lesson.complete', $lesson));

        $this->actingAs($this->student)
            ->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('Bài hoàn thành')
            ->assertSee('Tiến độ theo chủ đề');
    }

    public function test_draft_lesson_is_not_reachable(): void
    {
        $lesson = $this->sampleLesson();
        $lesson->update(['status' => Lesson::STATUS_DRAFT]);

        $this->actingAs($this->student)
            ->get(route('student.lesson.show', $lesson))
            ->assertNotFound();
    }
}
