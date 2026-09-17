<?php

namespace Database\Seeders;

use App\Models\Grade;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Câu hỏi mẫu đủ 6 loại cho chủ đề "Phép cộng phân số" (Lớp 6),
 * để luyện tập và test có dữ liệu thật để chạy.
 */
class SampleQuestionSeeder extends Seeder
{
    public function run(): void
    {
        $topic = Topic::where('slug', 'phep-cong-phan-so')->first();

        if (! $topic) {
            return;
        }

        $grade = Grade::where('level', 6)->firstOrFail();
        $author = User::where('email', 'teacher@toan-ai.local')->first();

        foreach ($this->questions() as $data) {
            $options = $data['options'] ?? null;
            unset($data['options']);

            $question = Question::updateOrCreate(
                ['content' => $data['content'], 'topic_id' => $topic->id],
                [...$data,
                    'grade_id' => $grade->id,
                    'topic_id' => $topic->id,
                    'status' => 'published',
                    'source' => 'manual',
                    'created_by' => $author?->id,
                ],
            );

            if ($options !== null) {
                $question->options()->delete();

                foreach ($options as $i => [$content, $isCorrect]) {
                    $question->options()->create([
                        'content' => $content,
                        'is_correct' => $isCorrect,
                        'sort_order' => $i + 1,
                    ]);
                }
            }
        }
    }

    /** @return array<int, array<string, mixed>> */
    private function questions(): array
    {
        return [
            [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'content' => '<p>Kết quả của $\frac{2}{7} + \frac{3}{7}$ là:</p>',
                'explanation' => '<p>Cùng mẫu số nên cộng tử, giữ nguyên mẫu: $\frac{2+3}{7} = \frac{5}{7}$.</p>',
                'difficulty' => 'easy',
                'points' => 1,
                'options' => [
                    ['$\frac{5}{7}$', true],
                    ['$\frac{5}{14}$', false],
                    ['$\frac{6}{7}$', false],
                    ['$\frac{5}{49}$', false],
                ],
            ],
            [
                'type' => Question::TYPE_SINGLE_CHOICE,
                'content' => '<p>Kết quả của $\frac{1}{2} + \frac{1}{3}$ là:</p>',
                'explanation' => '<p>Quy đồng mẫu 6: $\frac{3}{6} + \frac{2}{6} = \frac{5}{6}$.</p>',
                'difficulty' => 'medium',
                'points' => 1,
                'options' => [
                    ['$\frac{5}{6}$', true],
                    ['$\frac{2}{5}$', false],
                    ['$\frac{1}{6}$', false],
                    ['$\frac{2}{6}$', false],
                ],
            ],
            [
                'type' => Question::TYPE_MULTIPLE_CHOICE,
                'content' => '<p>Phân số nào bằng $\frac{1}{2}$?</p>',
                'explanation' => '<p>Rút gọn: $\frac{2}{4} = \frac{1}{2}$ và $\frac{3}{6} = \frac{1}{2}$.</p>',
                'difficulty' => 'medium',
                'points' => 2,
                'options' => [
                    ['$\frac{2}{4}$', true],
                    ['$\frac{3}{6}$', true],
                    ['$\frac{2}{5}$', false],
                    ['$\frac{4}{9}$', false],
                ],
            ],
            [
                'type' => Question::TYPE_TRUE_FALSE,
                'content' => '<p>Khẳng định sau đúng hay sai: $\frac{1}{2} + \frac{1}{3} = \frac{2}{5}$</p>',
                'explanation' => '<p>Sai. Không được cộng thẳng tử với tử, mẫu với mẫu — phải quy đồng trước.</p>',
                'difficulty' => 'easy',
                'points' => 1,
                'correct_answer' => ['value' => false],
            ],
            [
                'type' => Question::TYPE_FILL_BLANK,
                'content' => '<p>Quy đồng mẫu số 6: $\frac{1}{2} = \frac{?}{6}$ và $\frac{1}{3} = \frac{?}{6}$</p>',
                'explanation' => '<p>Nhân cả tử và mẫu: $\frac{1}{2} = \frac{3}{6}$, $\frac{1}{3} = \frac{2}{6}$.</p>',
                'difficulty' => 'medium',
                'points' => 2,
                'correct_answer' => ['blanks' => [['3'], ['2']]],
            ],
            [
                'type' => Question::TYPE_SHORT_ANSWER,
                'content' => '<p>Tính $\frac{1}{4} + \frac{1}{6}$ (viết dạng phân số tối giản).</p>',
                'explanation' => '<p>Mẫu chung 12: $\frac{3}{12} + \frac{2}{12} = \frac{5}{12}$.</p>',
                'difficulty' => 'hard',
                'points' => 2,
                'correct_answer' => ['accepted' => ['5/12']],
            ],
            [
                'type' => Question::TYPE_ESSAY,
                'content' => '<p>Trình bày các bước cộng hai phân số khác mẫu số và cho một ví dụ.</p>',
                'explanation' => '<p>Cần nêu: tìm mẫu chung, quy đồng, cộng tử, rút gọn.</p>',
                'difficulty' => 'hard',
                'points' => 3,
            ],
        ];
    }
}
