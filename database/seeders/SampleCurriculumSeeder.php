<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Một nhánh chương trình đầy đủ để demo và test: Lớp 6 → Toán → Phân số.
 * Không phải dữ liệu thật — nội dung thật sẽ nhập qua công cụ import ở Phase 2+.
 */
class SampleCurriculumSeeder extends Seeder
{
    public function run(): void
    {
        $grade = Grade::where('level', 6)->firstOrFail();
        $author = User::where('email', 'teacher@gmail.com')->first();

        $subject = Subject::updateOrCreate(
            ['grade_id' => $grade->id, 'slug' => 'toan'],
            ['name' => 'Toán', 'sort_order' => 1, 'is_active' => true],
        );

        $chapter = Chapter::updateOrCreate(
            ['subject_id' => $subject->id, 'slug' => 'phan-so'],
            ['name' => 'Phân số', 'sort_order' => 1, 'is_active' => true],
        );

        $topicAdd = Topic::updateOrCreate(
            ['chapter_id' => $chapter->id, 'slug' => 'phep-cong-phan-so'],
            ['name' => 'Phép cộng phân số', 'sort_order' => 1, 'is_active' => true],
        );

        Topic::updateOrCreate(
            ['chapter_id' => $chapter->id, 'slug' => 'so-sanh-phan-so'],
            ['name' => 'So sánh phân số', 'sort_order' => 2, 'is_active' => true],
        );

        $this->seedAddSameDenominator($topicAdd, $author?->id);
        $this->seedAddDifferentDenominator($topicAdd, $author?->id);
    }

    private function seedAddSameDenominator(Topic $topic, ?int $authorId): void
    {
        $lesson = Lesson::updateOrCreate(
            ['slug' => 'cong-hai-phan-so-cung-mau-so'],
            [
                'topic_id' => $topic->id,
                'title' => 'Cộng hai phân số cùng mẫu số',
                'summary' => 'Khi hai phân số đã chia bánh bằng nhau, chỉ cần cộng tử số.',
                'sort_order' => 1,
                'difficulty' => 'easy',
                'estimated_minutes' => 10,
                'access_level' => Lesson::ACCESS_FREE,
                'status' => Lesson::STATUS_PUBLISHED,
                'created_by' => $authorId,
                'published_at' => now(),
            ],
        );

        $this->replaceSections($lesson, [
            ['theory', 'Quy tắc', '<p>Muốn cộng hai phân số <strong>cùng mẫu số</strong>, ta cộng hai tử số và giữ nguyên mẫu số.</p><p>$$\frac{a}{m} + \frac{b}{m} = \frac{a+b}{m}$$</p>'],
            ['example', 'Ví dụ', '<p>$$\frac{2}{7} + \frac{3}{7} = \frac{2+3}{7} = \frac{5}{7}$$</p>'],
            ['insight', 'Hiểu bản chất', '<p>Cả hai phân số đều chia cái bánh thành 7 phần bằng nhau. Lấy 2 phần rồi lấy thêm 3 phần thì được 5 phần. Số phần chia (mẫu số) không đổi, nên mẫu số giữ nguyên.</p>'],
            ['common_mistake', 'Lỗi thường gặp', '<p>Cộng cả mẫu số: $\frac{2}{7} + \frac{3}{7} \ne \frac{5}{14}$. Mẫu số là <em>cách chia</em>, không phải số lượng để cộng.</p>'],
            ['practice', 'Luyện tập', '<p>Tính: $\frac{1}{5} + \frac{3}{5}$ và $\frac{4}{9} + \frac{2}{9}$.</p>'],
        ]);
    }

    private function seedAddDifferentDenominator(Topic $topic, ?int $authorId): void
    {
        $lesson = Lesson::updateOrCreate(
            ['slug' => 'cong-hai-phan-so-khac-mau-so'],
            [
                'topic_id' => $topic->id,
                'title' => 'Cộng hai phân số khác mẫu số',
                'summary' => 'Quy đồng mẫu số trước, rồi cộng như phân số cùng mẫu.',
                'sort_order' => 2,
                'difficulty' => 'medium',
                'estimated_minutes' => 15,
                'access_level' => Lesson::ACCESS_FREE,
                'status' => Lesson::STATUS_PUBLISHED,
                'created_by' => $authorId,
                'published_at' => now(),
            ],
        );

        $this->replaceSections($lesson, [
            ['theory', 'Quy tắc', '<p>Muốn cộng hai phân số <strong>khác mẫu số</strong>, ta quy đồng mẫu số rồi cộng như phân số cùng mẫu.</p>'],
            ['example', 'Ví dụ', '<p>$$\frac{1}{2} + \frac{1}{3} = \frac{3}{6} + \frac{2}{6} = \frac{5}{6}$$</p>'],
            ['insight', 'Hiểu bản chất', '<p>Hai cái bánh chia theo cách khác nhau thì không cộng thẳng được. Quy đồng chính là chia lại cả hai theo cùng một cách (cùng mẫu số) để so sánh và cộng được.</p>'],
            ['formula', 'Ghi nhớ', '<p>$$\frac{a}{b} + \frac{c}{d} = \frac{ad + bc}{bd}$$</p><p>Nên rút gọn kết quả nếu được.</p>'],
            ['common_mistake', 'Lỗi thường gặp', '<p>Cộng thẳng tử với tử, mẫu với mẫu: $\frac{1}{2} + \frac{1}{3} \ne \frac{2}{5}$.</p>'],
            ['quiz', 'Quiz nhanh', '<p>Mẫu số chung nhỏ nhất của 4 và 6 là bao nhiêu?</p>'],
            ['practice', 'Luyện tập', '<p>Tính: $\frac{1}{4} + \frac{1}{6}$ và $\frac{2}{3} + \frac{1}{5}$.</p>'],
            ['advanced', 'Bài nâng cao', '<p>Tính nhanh: $$\frac{1}{1\cdot 2} + \frac{1}{2\cdot 3} + \frac{1}{3\cdot 4}$$</p>'],
        ]);
    }

    /**
     * @param  list<array{0: string, 1: string, 2: string}>  $sections
     */
    private function replaceSections(Lesson $lesson, array $sections): void
    {
        $lesson->sections()->delete();

        foreach ($sections as $i => [$type, $title, $content]) {
            LessonSection::create([
                'lesson_id' => $lesson->id,
                'type' => $type,
                'title' => $title,
                'content' => $content,
                'sort_order' => $i + 1,
            ]);
        }
    }
}
