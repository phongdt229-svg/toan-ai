<?php

namespace Database\Seeders;

use App\Models\Assignment;
use App\Models\AssignmentStudent;
use App\Models\Chapter;
use App\Models\Grade;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\Role;
use App\Models\SchoolClass;
use App\Models\StudentProfile;
use App\Models\Subject;
use App\Models\Topic;
use App\Models\User;
use App\Services\Learning\MasteryService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Dữ liệu demo cho các màn hình thống kê (dashboard quản trị, báo cáo lớp của giáo viên):
 * thêm chủ đề + câu hỏi cho Lớp 6, một lớp học sinh và lịch sử luyện tập 30 ngày gần đây.
 *
 * Mastery KHÔNG được ghi thẳng — vẫn tính lại từ `question_attempts` qua MasteryService,
 * để số trên dashboard đúng bằng số hệ thống tự tính khi có người học thật.
 * Chỉ chạy ở local/testing (xem DatabaseSeeder). Chạy lại nhiều lần không nhân đôi dữ liệu.
 */
class SampleAnalyticsSeeder extends Seeder
{
    /** Số học sinh demo tạo thêm. */
    private const STUDENTS = 22;

    /** Hồ sơ năng lực từng chủ đề: [slug => [tên, tỉ lệ đúng trung bình của lớp]]. */
    private const TOPIC_SKILL = [
        'phep-cong-phan-so' => 0.78,
        'so-sanh-phan-so' => 0.70,
        'rut-gon-phan-so' => 0.62,
        'phep-tru-phan-so' => 0.55,
        'phep-nhan-phan-so' => 0.66,
        'phep-chia-phan-so' => 0.42,
        'cong-tru-so-nguyen' => 0.58,
        'thu-tu-thuc-hien-phep-tinh' => 0.35,
    ];

    public function run(): void
    {
        $grade = Grade::where('level', 6)->first();

        if (! $grade) {
            return;
        }

        // Cùng một bộ số ngẫu nhiên mỗi lần seed → ảnh chụp màn hình, demo không nhảy số lung tung.
        mt_srand(20260918);

        $this->seedCurriculum($grade);

        $students = $this->seedStudents($grade);
        $topics = Topic::whereIn('slug', array_keys(self::TOPIC_SKILL))->get()->keyBy('slug');

        $mastery = app(MasteryService::class);

        foreach ($students as $index => $student) {
            // Học sinh giỏi/kém khác nhau: hệ số nhân vào tỉ lệ đúng của từng chủ đề.
            // Dải rộng (0.7–1.45) để lớp có cả em khá lẫn em cần hỗ trợ, đủ dữ liệu cho các bộ lọc của giáo viên.
            $ability = 0.7 + ($index % 6) * 0.15;
            $topicIds = [];

            foreach (self::TOPIC_SKILL as $slug => $baseAccuracy) {
                $topic = $topics->get($slug);

                if (! $topic || QuestionAttempt::where('user_id', $student->id)->where('topic_id', $topic->id)->exists()) {
                    continue;
                }

                $this->seedAttempts($student, $topic, min(0.95, $baseAccuracy * $ability));
                $topicIds[] = $topic->id;
            }

            if ($topicIds) {
                $mastery->recalculateForTopics($student, $topicIds);
            }
        }

        $this->seedAssignmentResults($students);
    }

    /**
     * Cho học sinh demo nhận các bài đã giao của lớp mẫu và có kết quả — để báo cáo lớp của giáo viên
     * (tỉ lệ nộp bài, điểm trung bình, học sinh cần hỗ trợ) không phải toàn số 0.
     *
     * @param  list<User>  $students
     */
    private function seedAssignmentResults(array $students): void
    {
        $class = SchoolClass::where('code', 'TOAN6A')->first();

        if (! $class) {
            return;
        }

        $assignments = Assignment::where('class_id', $class->id)
            ->where('status', Assignment::STATUS_PUBLISHED)
            ->get();

        $inClass = $class->activeStudents()->pluck('users.id')->all();

        foreach ($assignments as $assignment) {
            foreach ($students as $index => $student) {
                if (! in_array($student->id, $inClass, true)) {
                    continue;
                }

                if (AssignmentStudent::where('assignment_id', $assignment->id)->where('student_id', $student->id)->exists()) {
                    continue;
                }

                // ~1/5 số lượt chưa làm → giáo viên thấy được ai đang bỏ bài.
                $done = mt_rand(1, 100) > 20;
                $percent = $done ? min(100, max(20, (int) round(45 + $index * 4 + mt_rand(-12, 12)))) : null;
                $submittedAt = $assignment->due_at
                    ? $assignment->due_at->copy()->subDays(mt_rand(0, 3))->setTime(mt_rand(18, 22), mt_rand(0, 59))
                    : now()->subDays(mt_rand(1, 10));

                AssignmentStudent::create([
                    'assignment_id' => $assignment->id,
                    'student_id' => $student->id,
                    'status' => $done ? AssignmentStudent::STATUS_COMPLETED : AssignmentStudent::STATUS_ASSIGNED,
                    'score' => $percent !== null ? round($percent / 10, 2) : null,
                    'max_score' => 10,
                    'percent' => $percent,
                    'attempts_count' => $done ? 1 : 0,
                    'time_spent_seconds' => $done ? mt_rand(300, 1800) : 0,
                    'is_late' => $done && $assignment->due_at && $submittedAt->gt($assignment->due_at),
                    'completed_at' => $done ? $submittedAt : null,
                ]);
            }
        }
    }

    /** Thêm chủ đề + câu hỏi cho Lớp 6 (giữ nguyên phần SampleCurriculumSeeder đã tạo). */
    private function seedCurriculum(Grade $grade): void
    {
        $subject = Subject::firstOrCreate(
            ['grade_id' => $grade->id, 'slug' => 'toan'],
            ['name' => 'Toán', 'sort_order' => 1, 'is_active' => true],
        );

        $fractions = Chapter::firstOrCreate(
            ['subject_id' => $subject->id, 'slug' => 'phan-so'],
            ['name' => 'Phân số', 'sort_order' => 1, 'is_active' => true],
        );

        $integers = Chapter::firstOrCreate(
            ['subject_id' => $subject->id, 'slug' => 'so-nguyen'],
            ['name' => 'Số nguyên', 'sort_order' => 2, 'is_active' => true],
        );

        $author = User::where('email', 'teacher@gmail.com')->value('id');

        $topics = [
            [$fractions, 'rut-gon-phan-so', 'Rút gọn phân số', 3, fn () => $this->simplifyQuestion()],
            [$fractions, 'phep-tru-phan-so', 'Phép trừ phân số', 4, fn () => $this->fractionQuestion('-')],
            [$fractions, 'phep-nhan-phan-so', 'Phép nhân phân số', 5, fn () => $this->fractionQuestion('*')],
            [$fractions, 'phep-chia-phan-so', 'Phép chia phân số', 6, fn () => $this->fractionQuestion(':')],
            [$integers, 'cong-tru-so-nguyen', 'Cộng, trừ số nguyên', 1, fn () => $this->integerQuestion()],
            [$integers, 'thu-tu-thuc-hien-phep-tinh', 'Thứ tự thực hiện phép tính', 2, fn () => $this->orderOfOperationsQuestion()],
        ];

        foreach ($topics as [$chapter, $slug, $name, $order, $maker]) {
            $topic = Topic::firstOrCreate(
                ['chapter_id' => $chapter->id, 'slug' => $slug],
                ['name' => $name, 'sort_order' => $order, 'is_active' => true],
            );

            // Mỗi chủ đề 6 câu là đủ để luyện tập và để mastery có ý nghĩa (ngưỡng 5 câu).
            for ($i = $topic->questions()->count(); $i < 6; $i++) {
                $this->createQuestion($topic, $grade, $author, $maker());
            }
        }
    }

    /** @param  array{content: string, explanation: string, difficulty: string, options: list<array{0: string, 1: bool}>}  $data */
    private function createQuestion(Topic $topic, Grade $grade, ?int $authorId, array $data): void
    {
        $question = Question::firstOrCreate(
            ['topic_id' => $topic->id, 'content' => $data['content']],
            [
                'grade_id' => $grade->id,
                'type' => Question::TYPE_SINGLE_CHOICE,
                'explanation' => $data['explanation'],
                'difficulty' => $data['difficulty'],
                'points' => 1,
                'status' => 'published',
                'source' => 'manual',
                'created_by' => $authorId,
            ],
        );

        if ($question->wasRecentlyCreated) {
            foreach ($data['options'] as $i => [$content, $isCorrect]) {
                $question->options()->create(['content' => $content, 'is_correct' => $isCorrect, 'sort_order' => $i + 1]);
            }
        }
    }

    /** @return list<User> */
    private function seedStudents(Grade $grade): array
    {
        $class = SchoolClass::where('code', 'TOAN6A')->first();
        $names = [
            'Nguyễn Gia Bảo', 'Trần Khánh Chi', 'Lê Minh Đức', 'Phạm Thu Hà', 'Hoàng Nam Khánh', 'Đỗ Bảo Lâm',
            'Vũ Thảo My', 'Bùi Quang Nam', 'Đặng Hải Phong', 'Ngô Ánh Tuyết', 'Dương Tiến Vũ', 'Lý Yến Nhi',
            'Đặng Minh Anh', 'Bùi Thanh Tùng', 'Vũ Ngọc Diệp', 'Trịnh Gia Hân', 'Lương Đức Anh', 'Phan Bảo Châu',
            'Đinh Xuân Mai', 'Hồ Quốc Việt', 'Chu Thảo Vy', 'Mai Anh Khoa',
        ];
        $students = [];

        for ($i = 0; $i < self::STUDENTS; $i++) {
            $email = sprintf('hs%02d@gmail.com', $i + 1);

            $student = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => $names[$i] ?? 'Học sinh '.($i + 1),
                    'password' => Hash::make(config('app.demo_password')),
                    'status' => User::STATUS_ACTIVE,
                    'email_verified_at' => now(),
                ],
            );

            if ($student->wasRecentlyCreated) {
                $student->assignRole(Role::STUDENT);
                StudentProfile::create([
                    'user_id' => $student->id,
                    'grade_id' => $grade->id,
                    'link_code' => StudentProfile::generateLinkCode(),
                ]);
            }

            // Hai phần ba lớp vào lớp mẫu 6A1 để báo cáo của giáo viên cũng có dữ liệu.
            if ($class && $i % 3 !== 2 && ! $class->activeStudents()->where('users.id', $student->id)->exists()) {
                $class->students()->syncWithoutDetaching([
                    $student->id => ['status' => 'active', 'joined_at' => now()->subDays(30)],
                ]);
            }

            $students[] = $student;
        }

        return $students;
    }

    /** Lịch sử luyện tập 30 ngày gần đây của một học sinh cho một chủ đề. */
    private function seedAttempts(User $student, Topic $topic, float $accuracy): void
    {
        $questions = Question::where('topic_id', $topic->id)->published()->get(['id', 'difficulty', 'points']);

        if ($questions->isEmpty()) {
            return;
        }

        $rows = [];
        $attemptNo = [];
        $count = mt_rand(6, 14);

        for ($i = 0; $i < $count; $i++) {
            /** @var Question $question */
            $question = $questions[mt_rand(0, $questions->count() - 1)];
            $correct = (mt_rand(1, 100) / 100) <= $accuracy;
            // Rải đều 30 ngày, nhưng dồn nhiều hơn về tuần gần nhất như người học thật.
            $daysAgo = mt_rand(0, 100) < 45 ? mt_rand(0, 6) : mt_rand(7, 29);
            $at = now()->subDays($daysAgo)->setTime(mt_rand(7, 21), mt_rand(0, 59));
            $no = ($attemptNo[$question->id] = ($attemptNo[$question->id] ?? 0) + 1);

            $rows[] = [
                'user_id' => $student->id,
                'question_id' => $question->id,
                'topic_id' => $topic->id,
                'context' => QuestionAttempt::CONTEXT_PRACTICE,
                'difficulty' => $question->difficulty,
                'answer' => json_encode(['value' => $correct ? 'A' : 'B']),
                'is_correct' => $correct,
                'score' => $correct ? $question->points : 0,
                'time_spent_seconds' => mt_rand(15, 150),
                'attempt_no' => $no,
                'created_at' => $at,
                'updated_at' => $at,
            ];
        }

        DB::table('question_attempts')->insert($rows);
    }

    // --- Sinh câu hỏi (tự tính đáp án nên luôn đúng) --------------------------------------

    /** @return array{content: string, explanation: string, difficulty: string, options: list<array{0: string, 1: bool}>} */
    private function fractionQuestion(string $op): array
    {
        [$a, $b] = $this->properFraction();
        [$c, $d] = $this->properFraction();

        [$num, $den, $label, $explain] = match ($op) {
            '-' => [$a * $d - $c * $b, $b * $d, '-', 'Quy đồng mẫu số rồi trừ tử số.'],
            '*' => [$a * $c, $b * $d, '\times', 'Nhân tử với tử, mẫu với mẫu.'],
            ':' => [$a * $d, $b * $c, ':', 'Chia phân số = nhân với phân số nghịch đảo.'],
            default => [$a * $d + $c * $b, $b * $d, '+', 'Quy đồng mẫu số rồi cộng tử số.'],
        };

        $correct = $this->fraction($num, $den);
        $wrong = match ($op) {
            '*' => [$this->fraction($a + $c, $b + $d), $this->fraction($a * $d, $b * $c), $this->fraction($num, $den + 1)],
            ':' => [$this->fraction($a * $c, $b * $d), $this->fraction($b * $c, $a * $d), $this->fraction($num + 1, $den)],
            default => [$this->fraction($a - $c, $b - $d ?: 1), $this->fraction($a * $c, $b * $d), $this->fraction($num, $b + $d)],
        };

        return [
            'content' => "<p>Tính: \$\\frac{{$a}}{{$b}} {$label} \\frac{{$c}}{{$d}}\$</p>",
            'explanation' => "<p>{$explain} Kết quả: \${$correct}\$.</p>",
            'difficulty' => $op === ':' ? 'hard' : 'medium',
            'options' => $this->options($correct, $wrong),
        ];
    }

    /** @return array{content: string, explanation: string, difficulty: string, options: list<array{0: string, 1: bool}>} */
    private function simplifyQuestion(): array
    {
        $k = mt_rand(2, 9);
        $a = mt_rand(2, 9);
        $b = $a + mt_rand(1, 7);
        $correct = $this->fraction($a, $b);

        return [
            'content' => '<p>Rút gọn phân số $\frac{'.($k * $a).'}{'.($k * $b).'}$</p>',
            'explanation' => "<p>Chia cả tử và mẫu cho ước chung {$k}: \${$correct}\$.</p>",
            'difficulty' => 'easy',
            'options' => $this->options($correct, [
                $this->fraction($k * $a, $b),
                $this->fraction($a, $k * $b),
                $this->fraction($a + 1, $b),
            ]),
        ];
    }

    /** @return array{content: string, explanation: string, difficulty: string, options: list<array{0: string, 1: bool}>} */
    private function integerQuestion(): array
    {
        $a = mt_rand(-20, -3);
        $b = mt_rand(4, 25);
        $result = $a + $b;

        return [
            'content' => "<p>Tính: \$({$a}) + {$b}\$</p>",
            'explanation' => '<p>Cộng số nguyên khác dấu: lấy hiệu hai giá trị tuyệt đối, giữ dấu của số có giá trị tuyệt đối lớn hơn. '
                ."Kết quả: \${$result}\$.</p>",
            'difficulty' => 'medium',
            'options' => $this->options((string) $result, [
                (string) ($a - $b),
                (string) (abs($a) + $b),
                (string) (-$result),
            ]),
        ];
    }

    /** @return array{content: string, explanation: string, difficulty: string, options: list<array{0: string, 1: bool}>} */
    private function orderOfOperationsQuestion(): array
    {
        [$a, $b, $c] = [mt_rand(2, 9), mt_rand(2, 9), mt_rand(2, 9)];
        $result = $a + $b * $c;

        return [
            'content' => "<p>Tính: \${$a} + {$b} \\times {$c}\$</p>",
            'explanation' => "<p>Nhân chia trước, cộng trừ sau: {$b} × {$c} = ".($b * $c).", rồi cộng {$a} được \${$result}\$.</p>",
            'difficulty' => 'hard',
            'options' => $this->options((string) $result, [
                (string) (($a + $b) * $c),
                (string) ($a + $b + $c),
                (string) ($a * $b + $c),
            ]),
        ];
    }

    /**
     * Phân số thật sự (tử < mẫu) và đã tối giản — đề bài nhìn gọn, không ra kiểu $
rac{4}{4}$.
     *
     * @return array{0: int, 1: int}
     */
    private function properFraction(): array
    {
        do {
            $den = mt_rand(3, 12);
            $num = mt_rand(1, $den - 1);
        } while ($this->gcd($num, $den) !== 1);

        return [$num, $den];
    }

    /** Phân số đã rút gọn, dạng LaTeX; mẫu 1 hoặc tử 0 thì hiện số nguyên. */
    private function fraction(int $num, int $den): string
    {
        if ($den === 0) {
            return '0';
        }

        if ($den < 0) {
            [$num, $den] = [-$num, -$den];
        }

        $g = max(1, $this->gcd(abs($num), abs($den)));
        $num /= $g;
        $den /= $g;

        if ($num === 0) {
            return '0';
        }

        return $den === 1 ? (string) $num : ($num < 0 ? '-\frac{'.abs($num).'}{'.$den.'}' : '\frac{'.$num.'}{'.$den.'}');
    }

    private function gcd(int $a, int $b): int
    {
        return $b === 0 ? $a : $this->gcd($b, $a % $b);
    }

    /**
     * Trộn đáp án đúng vào các phương án nhiễu, bỏ nhiễu trùng đáp án đúng.
     *
     * @param  list<string>  $wrong
     * @return list<array{0: string, 1: bool}>
     */
    private function options(string $correct, array $wrong): array
    {
        $options = [[$correct, true]];

        foreach (array_unique($wrong) as $value) {
            if ($value !== $correct && count($options) < 4) {
                $options[] = [$value, false];
            }
        }

        // Thiếu nhiễu (do trùng) thì bù bằng phương án "Không có đáp án đúng".
        while (count($options) < 4) {
            $options[] = ['Không có đáp án đúng', false];
        }

        $keys = array_keys($options);
        shuffle($keys);

        return array_map(fn ($k) => [$this->wrapMath($options[$k][0]), $options[$k][1]], $keys);
    }

    /** Bọc $...$ cho phần có LaTeX để KaTeX render. */
    private function wrapMath(string $value): string
    {
        return str_contains($value, '\frac') || is_numeric($value) ? "\${$value}\$" : $value;
    }
}
