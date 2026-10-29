<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Chương trình công khai (/chuong-trinh) — GEO. Điều quan trọng nhất cần canh: chỉ lộ CẤU TRÚC,
 * nội dung bài vẫn phải đăng nhập (spec §6).
 */
class PublicCurriculumTest extends TestCase
{
    use RefreshDatabase;

    private Grade $grade;

    private Chapter $chapter;

    private Topic $topic;

    private Lesson $lesson;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
        config(['learning.grade_min' => 1, 'learning.grade_max' => 12]);

        $this->grade = Grade::where('level', 6)->firstOrFail();
        $subject = Subject::create(['grade_id' => $this->grade->id, 'name' => 'Toán', 'slug' => 'toan']);
        $this->chapter = Chapter::create(['subject_id' => $subject->id, 'name' => 'Chương 2. Phân số', 'slug' => 'phan-so']);
        $this->topic = Topic::create(['chapter_id' => $this->chapter->id, 'name' => 'Phép cộng phân số', 'slug' => 'phep-cong']);
        $this->lesson = $this->lesson('Cộng hai phân số khác mẫu', 'cong-khac-mau', ['summary' => 'Quy đồng rồi cộng tử số.']);

        LessonSection::create(['lesson_id' => $this->lesson->id, 'type' => 'theory', 'title' => 'Quy đồng mẫu số',
            'content' => '<p>NOI-DUNG-BI-MAT-CHI-HOC-SINH</p>', 'sort_order' => 1]);
        LessonSection::create(['lesson_id' => $this->lesson->id, 'type' => 'practice', 'title' => null,
            'content' => '<p>BAI-TAP-BI-MAT</p>', 'sort_order' => 2]);
    }

    private function lesson(string $title, string $slug, array $extra = []): Lesson
    {
        return Lesson::create([
            'topic_id' => $this->topic->id, 'title' => $title, 'slug' => $slug, 'difficulty' => 'medium',
            'estimated_minutes' => 15, 'access_level' => Lesson::ACCESS_FREE,
            'status' => Lesson::STATUS_PUBLISHED, 'published_at' => now(), ...$extra,
        ]);
    }

    public function test_index_lists_grades_with_lesson_counts(): void
    {
        $this->get(route('curriculum.index'))
            ->assertOk()
            ->assertSee('Toán lớp 6')
            ->assertSee('1 bài học')
            ->assertSee('"@type":"ItemList"', false);
    }

    public function test_grade_page_shows_structure_and_course_schema(): void
    {
        $this->get(route('curriculum.grade', $this->grade))
            ->assertOk()
            ->assertSee('Chương 2. Phân số')
            ->assertSee('Phép cộng phân số')
            ->assertSee('Cộng hai phân số khác mẫu')
            ->assertSee('"@type":"Course"', false)
            ->assertSee('<meta name="robots" content="index,follow">', false);
    }

    public function test_grade_without_lessons_is_noindex(): void
    {
        $empty = Grade::where('level', 7)->firstOrFail();

        $this->get(route('curriculum.grade', $empty))
            ->assertOk()
            ->assertSee('<meta name="robots" content="noindex,follow">', false)
            ->assertDontSee('"@type":"Course"', false);
    }

    public function test_lesson_page_shows_outline_but_never_section_content(): void
    {
        $this->get(route('curriculum.lesson', [$this->grade, $this->lesson->slug]))
            ->assertOk()
            ->assertSee('Quy đồng mẫu số')
            ->assertSee('Luyện tập')
            ->assertSee('Vui lòng đăng nhập để bắt đầu học.')
            ->assertSee('"@type":"LearningResource"', false)
            ->assertSee('"isAccessibleForFree":true', false)
            ->assertDontSee('NOI-DUNG-BI-MAT-CHI-HOC-SINH')
            ->assertDontSee('BAI-TAP-BI-MAT');
    }

    public function test_logged_in_student_gets_link_to_real_lesson(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole(Role::STUDENT);
        StudentProfile::create(['user_id' => $student->id, 'grade_id' => $this->grade->id, 'link_code' => StudentProfile::generateLinkCode()]);

        $this->actingAs($student)->get(route('curriculum.lesson', [$this->grade, $this->lesson->slug]))
            ->assertOk()
            ->assertSee(route('student.lesson.show', $this->lesson->slug), false)
            ->assertDontSee('NOI-DUNG-BI-MAT-CHI-HOC-SINH');
    }

    public function test_unpublished_disabled_or_wrong_grade_lessons_are_404(): void
    {
        $draft = $this->lesson('Bài nháp', 'bai-nhap', ['status' => Lesson::STATUS_DRAFT, 'published_at' => null]);
        $this->get(route('curriculum.lesson', [$this->grade, $draft->slug]))->assertNotFound();

        // Đúng bài nhưng sai lớp trên URL — không cho đoán slug ở lớp khác.
        $other = Grade::where('level', 7)->firstOrFail();
        $this->get(route('curriculum.lesson', [$other, $this->lesson->slug]))->assertNotFound();

        $this->chapter->update(['is_active' => false]);
        $this->get(route('curriculum.lesson', [$this->grade, $this->lesson->slug]))->assertNotFound();
        $this->get(route('curriculum.grade', $this->grade))->assertOk()->assertDontSee('Cộng hai phân số khác mẫu');
    }

    public function test_grade_outside_accepted_range_is_404(): void
    {
        config(['learning.grade_min' => 6]);

        $this->get(route('curriculum.grade', Grade::where('level', 3)->firstOrFail()))->assertNotFound();
    }

    public function test_sitemap_and_llms_list_public_lessons(): void
    {
        $lessonUrl = route('curriculum.lesson', [$this->grade, $this->lesson->slug]);

        $this->get(route('sitemap'))->assertOk()
            ->assertSee('<loc>'.route('curriculum.index').'</loc>', false)
            ->assertSee('<loc>'.route('curriculum.grade', $this->grade).'</loc>', false)
            ->assertSee('<loc>'.$lessonUrl.'</loc>', false)
            // Lớp chưa có bài (noindex) không được nằm trong sitemap.
            ->assertDontSee('<loc>'.route('curriculum.grade', Grade::where('level', 7)->first()).'</loc>', false);

        $this->get(route('llms'))->assertOk()
            ->assertSee('## Chương trình học')
            ->assertSee('[Cộng hai phân số khác mẫu]('.$lessonUrl.'): Quy đồng rồi cộng tử số.');
    }

    public function test_landing_grade_pills_link_to_public_curriculum(): void
    {
        $this->get('/')->assertOk()->assertSee(route('curriculum.grade', $this->grade), false);
    }
}
