<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Lesson;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Nội dung mẫu cho chủ đề thứ hai ("So sánh phân số") — để kiểm tra đầu vào trải được
 * nhiều chủ đề và lộ trình demo có đủ các giai đoạn. Chỉ chạy ở local.
 */
class SamplePathSeeder extends Seeder
{
    public function run(): void
    {
        $topic = Topic::where('slug', 'so-sanh-phan-so')->first();
        $grade = Grade::where('level', 6)->first();

        if (! $topic || ! $grade) {
            return;
        }

        $author = User::where('email', 'teacher@toan-ai.local')->value('id');

        $lesson = Lesson::updateOrCreate(['slug' => 'so-sanh-hai-phan-so'], [
            'topic_id' => $topic->id,
            'title' => 'So sánh hai phân số',
            'summary' => 'Cùng mẫu thì so tử; khác mẫu thì quy đồng rồi so.',
            'sort_order' => 1,
            'difficulty' => 'medium',
            'estimated_minutes' => 12,
            'status' => Lesson::STATUS_PUBLISHED,
            'created_by' => $author,
            'published_at' => now(),
        ]);

        if ($lesson->sections()->doesntExist()) {
            $lesson->sections()->createMany([
                ['type' => 'theory', 'title' => 'Quy tắc', 'sort_order' => 1,
                    'content' => '<p>Hai phân số cùng mẫu (mẫu dương): phân số nào có tử lớn hơn thì lớn hơn.</p><p>Khác mẫu: quy đồng mẫu số rồi so sánh tử.</p>'],
                ['type' => 'example', 'title' => 'Ví dụ', 'sort_order' => 2,
                    'content' => '<p>$\\frac{2}{3}$ và $\\frac{3}{4}$: quy đồng được $\\frac{8}{12} < \\frac{9}{12}$ nên $\\frac{2}{3} < \\frac{3}{4}$.</p>'],
                ['type' => 'common_mistake', 'title' => 'Lỗi thường gặp', 'sort_order' => 3,
                    'content' => '<p>Nghĩ rằng mẫu lớn hơn thì phân số lớn hơn: $\\frac{1}{5}$ nhỏ hơn $\\frac{1}{2}$.</p>'],
            ]);
        }

        $questions = [
            ['easy', Question::TYPE_TRUE_FALSE, '<p>$\\frac{3}{7} > \\frac{2}{7}$</p>', ['value' => true], null],
            ['easy', Question::TYPE_SINGLE_CHOICE, '<p>Phân số nào lớn nhất?</p>', null,
                [['$\\frac{5}{9}$', true], ['$\\frac{2}{9}$', false], ['$\\frac{4}{9}$', false]]],
            ['medium', Question::TYPE_SINGLE_CHOICE, '<p>So sánh $\\frac{2}{3}$ và $\\frac{3}{4}$:</p>', null,
                [['$\\frac{2}{3} < \\frac{3}{4}$', true], ['$\\frac{2}{3} > \\frac{3}{4}$', false], ['Bằng nhau', false]]],
            ['medium', Question::TYPE_TRUE_FALSE, '<p>$\\frac{1}{5} > \\frac{1}{2}$ vì $5 > 2$.</p>', ['value' => false], null],
            ['hard', Question::TYPE_SHORT_ANSWER, '<p>Viết phân số lớn nhất trong $\\frac{5}{6}, \\frac{7}{9}, \\frac{11}{12}$.</p>', ['accepted' => ['11/12']], null],
            ['hard', Question::TYPE_SINGLE_CHOICE, '<p>Sắp xếp tăng dần $\\frac{1}{2}, \\frac{2}{5}, \\frac{3}{4}$:</p>', null,
                [['$\\frac{2}{5}, \\frac{1}{2}, \\frac{3}{4}$', true], ['$\\frac{1}{2}, \\frac{2}{5}, \\frac{3}{4}$', false]]],
        ];

        foreach ($questions as [$difficulty, $type, $content, $answer, $options]) {
            $q = Question::updateOrCreate(['topic_id' => $topic->id, 'content' => $content], [
                'grade_id' => $grade->id,
                'type' => $type,
                'difficulty' => $difficulty,
                'correct_answer' => $answer,
                'points' => 1,
                'status' => 'published',
                'source' => 'manual',
                'created_by' => $author,
            ]);

            if ($options && $q->options()->doesntExist()) {
                foreach ($options as $i => [$text, $correct]) {
                    $q->options()->create(['content' => $text, 'is_correct' => $correct, 'sort_order' => $i + 1]);
                }
            }
        }
    }
}
