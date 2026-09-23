<?php

namespace Database\Seeders;

use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Nạp khung chương trình Toán 1–12 từ `database/data/curriculum.php`.
 *
 * Đây là thứ đang chặn việc nhập nội dung: không có chủ đề thì màn hình soạn bài
 * của giáo viên không chọn được gì, và câu hỏi cũng không biết treo vào đâu.
 *
 * Idempotent theo slug — chạy lại không tạo trùng. KHÔNG xoá hay tắt chương/chủ đề
 * do người dùng tự thêm: trường học mỗi nơi chia chương một khác, sửa tay ở
 * Quản trị → Chương trình là chuyện bình thường và không được ghi đè.
 */
class CurriculumSkeletonSeeder extends Seeder
{
    public function run(): void
    {
        /** @var array<int, array<int, array{name: string, topics: array<int, string>}>> $data */
        $data = require database_path('data/curriculum.php');

        $chapters = 0;
        $topics = 0;

        foreach ($data as $level => $chapterList) {
            $grade = Grade::where('level', $level)->first();

            if (! $grade) {
                $this->command?->warn("Bỏ qua lớp {$level}: chưa có trong bảng grades (chạy GradeSeeder trước).");

                continue;
            }

            $subject = Subject::updateOrCreate(
                ['grade_id' => $grade->id, 'slug' => 'toan'],
                ['name' => 'Toán', 'sort_order' => 1, 'is_active' => true],
            );

            foreach ($chapterList as $chapterIndex => $row) {
                $chapter = Chapter::updateOrCreate(
                    ['subject_id' => $subject->id, 'slug' => Str::slug($row['name'])],
                    ['name' => $row['name'], 'sort_order' => $chapterIndex + 1, 'is_active' => true],
                );
                $chapters++;

                foreach ($row['topics'] as $topicIndex => $name) {
                    Topic::updateOrCreate(
                        ['chapter_id' => $chapter->id, 'slug' => Str::slug($name)],
                        ['name' => $name, 'sort_order' => $topicIndex + 1, 'is_active' => true],
                    );
                    $topics++;
                }
            }
        }

        $this->command?->info("Khung chương trình: {$chapters} chương, {$topics} chủ đề.");
        $this->command?->warn('Đây là bộ khung — nhờ giáo viên rà lại tên chương/chủ đề cho khớp bộ sách đang dùng.');
    }
}
