<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Topic;
use Database\Seeders\CurriculumSkeletonSeeder;
use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Khung chương trình Toán 1–12 (đợt 23/09).
 *
 * Không có chủ đề thì màn hình soạn bài của giáo viên không chọn được gì — đó là thứ
 * đang chặn việc nhập nội dung, nên bộ khung phải phủ đủ 12 lớp và chạy lại được nhiều lần.
 */
class CurriculumSkeletonTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(GradeSeeder::class);
    }

    public function test_every_grade_gets_chapters_and_topics(): void
    {
        $this->seed(CurriculumSkeletonSeeder::class);

        foreach (Grade::orderBy('level')->get() as $grade) {
            $chapters = Chapter::whereHas('subject', fn ($q) => $q->where('grade_id', $grade->id))->count();
            $topics = Topic::whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $grade->id))->count();

            $this->assertGreaterThanOrEqual(4, $chapters, "{$grade->name} có quá ít chương.");
            $this->assertGreaterThanOrEqual(10, $topics, "{$grade->name} có quá ít chủ đề.");
        }
    }

    public function test_running_it_twice_does_not_duplicate_anything(): void
    {
        $this->seed(CurriculumSkeletonSeeder::class);
        $chapters = Chapter::count();
        $topics = Topic::count();

        $this->seed(CurriculumSkeletonSeeder::class);

        $this->assertSame($chapters, Chapter::count());
        $this->assertSame($topics, Topic::count());
    }

    public function test_it_leaves_topics_added_by_hand_alone(): void
    {
        $this->seed(CurriculumSkeletonSeeder::class);

        $chapter = Chapter::first();
        $mine = Topic::create([
            'chapter_id' => $chapter->id,
            'name' => 'Chủ đề trường tôi tự thêm',
            'slug' => 'chu-de-truong-toi-tu-them',
            'sort_order' => 99,
            'is_active' => true,
        ]);

        $this->seed(CurriculumSkeletonSeeder::class);

        // Mỗi trường chia chương một khác — sửa tay là chuyện bình thường, không được ghi đè.
        $this->assertDatabaseHas('topics', ['id' => $mine->id, 'name' => 'Chủ đề trường tôi tự thêm']);
    }

    public function test_the_data_file_covers_all_twelve_grades(): void
    {
        $data = require database_path('data/curriculum.php');

        $this->assertSame(range(1, 12), array_keys($data));

        foreach ($data as $level => $chapters) {
            foreach ($chapters as $chapter) {
                $this->assertNotEmpty($chapter['name'], "Lớp {$level} có chương không tên.");
                $this->assertNotEmpty($chapter['topics'], "Chương {$chapter['name']} không có chủ đề nào.");
            }
        }
    }

    public function test_each_grade_has_a_maths_subject(): void
    {
        $this->seed(CurriculumSkeletonSeeder::class);

        $this->assertSame(
            Grade::count(),
            Subject::where('slug', 'toan')->count(),
            'Mỗi lớp phải có đúng một môn Toán.',
        );
    }
}
