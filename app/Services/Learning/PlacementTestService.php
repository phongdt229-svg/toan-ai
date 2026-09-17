<?php

namespace App\Services\Learning;

use App\Models\PlacementTest;
use App\Models\PlacementTestAnswer;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Models\StudentProfile;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\AiProviderException;
use App\Services\AI\AiRequest;
use App\Services\AI\AiUsageGuard;
use App\Services\AI\ContentGeneratorService;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\AI\PromptBuilder;
use App\Services\Teaching\ExamBuilderService;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Kiểm tra đầu vào (§34): ra đề theo lớp + học lực → học sinh làm → chấm, phân tích
 * → sinh lộ trình cá nhân hóa (§35).
 *
 * Đề lấy từ NGÂN HÀNG CÂU HỎI (đáp án đã được giáo viên duyệt → chấm chắc chắn đúng).
 * Chỉ khi ngân hàng không đủ mới nhờ AI sinh bù.
 */
class PlacementTestService
{
    public const TARGET_QUESTIONS = 8;

    public const MIN_QUESTIONS = 5;

    public const DURATION_MINUTES = 20;

    /** Tỉ lệ Dễ/TB/Khó theo học lực tự đánh giá lúc đăng ký (§33). */
    public const MIX = [
        'average' => ['easy' => 4, 'medium' => 3, 'hard' => 1],
        'good' => ['easy' => 2, 'medium' => 4, 'hard' => 2],
        'excellent' => ['easy' => 1, 'medium' => 3, 'hard' => 4],
    ];

    /** Dưới mức này (% đúng trong đề) là chủ đề yếu. */
    public const WEAK_TOPIC_PERCENT = 50;

    private const AUTO_TYPES = [
        Question::TYPE_SINGLE_CHOICE, Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TRUE_FALSE,
        Question::TYPE_FILL_BLANK, Question::TYPE_SHORT_ANSWER,
    ];

    public function __construct(
        private readonly GradingService $grading,
        private readonly MasteryService $mastery,
        private readonly LearningPathService $paths,
        private readonly AiProviderInterface $provider,
        private readonly AiUsageGuard $usage,
        private readonly ContentGeneratorService $generator,
        private readonly ExamBuilderService $examBuilder,
        private readonly PromptBuilder $prompts,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    public function latest(User $student): ?PlacementTest
    {
        return PlacementTest::where('user_id', $student->id)->latest('id')->first();
    }

    // --- Ra đề ---------------------------------------------------------------------------

    public function start(User $student): PlacementTest
    {
        $profile = $student->loadMissing('studentProfile.grade')->studentProfile;

        if (! $profile?->grade_id) {
            throw new PlacementException('Em cần chọn lớp trong hồ sơ trước khi làm kiểm tra đầu vào.');
        }

        if ($existing = $this->resumable($student)) {
            return $existing;
        }

        // Dựng đề NGOÀI transaction: có thể phải gọi AI (vài giây), không giữ khoá DB lâu như vậy.
        $snapshots = $this->pickFromBank($profile->grade_id, $profile->self_assessed_level);

        if ($snapshots->count() < self::MIN_QUESTIONS) {
            $snapshots = $snapshots->merge(
                $this->generateMissing($student, $profile->grade_id, self::TARGET_QUESTIONS - $snapshots->count()),
            );
        }

        if ($snapshots->count() < self::MIN_QUESTIONS) {
            throw new PlacementException('Chương trình lớp của em chưa đủ câu hỏi để làm kiểm tra đầu vào. Em quay lại sau nhé.');
        }

        return DB::transaction(function () use ($student, $profile, $snapshots) {
            // Hai tab bấm "Bắt đầu" cùng lúc: tab sau nhận lại bài của tab trước.
            User::whereKey($student->id)->lockForUpdate()->first();

            if ($existing = $this->resumable($student)) {
                return $existing;
            }

            $now = now();
            $test = PlacementTest::create([
                'user_id' => $student->id,
                'grade_id' => $profile->grade_id,
                'status' => PlacementTest::STATUS_IN_PROGRESS,
                'started_at' => $now,
                'expires_at' => $now->copy()->addMinutes(self::DURATION_MINUTES),
                'total_questions' => $snapshots->count(),
            ]);

            foreach ($snapshots->values() as $i => $snapshot) {
                $test->questions()->create([...$snapshot, 'sort_order' => $i + 1]);
            }

            return $test;
        });
    }

    /** Bài đang làm dở còn giờ; quá giờ thì chốt luôn và coi như không còn bài dở. */
    private function resumable(User $student): ?PlacementTest
    {
        $current = PlacementTest::where('user_id', $student->id)
            ->where('status', PlacementTest::STATUS_IN_PROGRESS)
            ->first();

        if ($current && $current->isPastDeadline()) {
            $this->submit($current, [], [], auto: true);

            return null;
        }

        return $current;
    }

    /**
     * Bốc câu theo tỉ lệ độ khó, trải đều các chủ đề (để phát hiện được điểm yếu ở nhiều chủ đề).
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function pickFromBank(int $gradeId, ?string $level): Collection
    {
        $mix = self::MIX[$level] ?? self::MIX['good'];

        $pool = Question::published()
            ->where('grade_id', $gradeId)
            ->whereNotNull('topic_id')
            ->whereIn('type', self::AUTO_TYPES)
            ->with('options')
            ->get()
            ->shuffle();

        $picked = collect();
        foreach ($mix as $difficulty => $count) {
            $available = $pool->where('difficulty', $difficulty)->whereNotIn('id', $picked->pluck('id'));
            $picked = $picked->merge($this->spreadByTopic($available, $count));
        }

        // Thiếu câu ở một mức khó → bù bằng mức khác, vẫn trải đều chủ đề.
        if ($picked->count() < self::TARGET_QUESTIONS) {
            $picked = $picked->merge($this->spreadByTopic(
                $pool->whereNotIn('id', $picked->pluck('id')),
                self::TARGET_QUESTIONS - $picked->count(),
            ));
        }

        return $picked->map(fn (Question $q) => [
            'question_id' => $q->id,
            'topic_id' => $q->topic_id,
            'type' => $q->type,
            'difficulty' => $q->difficulty,
            'content' => $q->content,
            'options' => $q->usesOptions()
                ? $q->options->map(fn ($o) => ['id' => $o->id, 'content' => $o->content, 'is_correct' => (bool) $o->is_correct])->values()->all()
                : null,
            'correct_answer' => $q->correct_answer,
            'explanation' => $q->explanation,
            // Mọi câu 1 điểm: đề đầu vào đo trình độ, không phải tính điểm theo thang của giáo viên.
            'points' => 1,
        ])->values();
    }

    /** Lấy luân phiên một câu từ mỗi chủ đề cho tới đủ số lượng. */
    private function spreadByTopic(Collection $questions, int $count): Collection
    {
        $groups = $questions->groupBy('topic_id')->map->values()->values();
        $picked = collect();

        for ($round = 0; $picked->count() < $count; $round++) {
            $added = false;

            foreach ($groups as $group) {
                if ($picked->count() >= $count) {
                    break;
                }
                if ($question = $group->get($round)) {
                    $picked->push($question);
                    $added = true;
                }
            }

            if (! $added) {
                break;
            }
        }

        return $picked;
    }

    /**
     * Ngân hàng thiếu câu → AI sinh bù. AI lỗi thì trả rỗng: học sinh nhận thông báo "chưa đủ câu",
     * không bao giờ nhận một đề hỏng.
     *
     * @return Collection<int, array<string, mixed>>
     */
    private function generateMissing(User $student, int $gradeId, int $count): Collection
    {
        $topics = Topic::whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $gradeId))->with('chapter')->get();

        if ($topics->isEmpty() || $count <= 0) {
            return collect();
        }

        $plan = array_filter($this->examBuilder->quotas($count, ['easy' => 30, 'medium' => 50, 'hard' => 20]));
        $topicNames = $topics->pluck('name')->implode('; ');

        try {
            $response = $this->provider->complete(new AiRequest(
                messages: [
                    ['role' => 'system', 'content' => "Bạn là giáo viên Toán Việt Nam soạn đề kiểm tra đầu vào cho {$student->studentProfile->grade?->name}. Công thức dùng LaTeX \$...\$. Chỉ trả JSON hợp lệ, đáp án phải chính xác."],
                    ['role' => 'user', 'content' => 'Tạo '.collect($plan)->map(fn ($n, $d) => "{$n} câu {$d}")->implode(', ')
                        .", trải đều các chủ đề: {$topicNames}."
                        ."\nChỉ dùng loại: ".implode(', ', self::AUTO_TYPES)
                        ."\nJSON: {\"questions\": [{\"type\", \"difficulty\", \"topic\" (đúng một tên chủ đề ở trên), \"content\", \"explanation\", "
                        ."\"options\"+\"correct\" (chỉ số từ 0) cho trắc nghiệm | \"correct\": true/false | \"blanks\": [[...]] | \"accepted\": [...]}]}"],
                ],
                task: 'generate_questions',
                maxTokens: config('ai.max_output_tokens.generate_questions'),
                json: true,
                meta: ['plan' => $plan],
            ));
        } catch (AiProviderException $e) {
            $this->usage->recordFailure($student, 'placement_generate');
            Log::warning('Placement AI generation failed', ['user' => $student->id, 'reason' => $e->reason]);

            return collect();
        }

        // Ghi chi phí nhưng KHÔNG trừ quota hỏi AI của học sinh — kiểm tra đầu vào là việc hệ thống yêu cầu.
        $this->usage->record($student, 'placement_generate', $response);

        try {
            $raw = $response->json()['questions'] ?? [];
        } catch (AiProviderException) {
            return collect();
        }

        $byName = $topics->keyBy(fn ($t) => mb_strtolower($t->name));

        return collect(is_array($raw) ? $raw : [])
            ->map(fn ($candidate) => [$candidate, $this->generator->normalizeQuestion($candidate, self::AUTO_TYPES)])
            ->filter(fn ($pair) => $pair[1] !== null)
            ->values()
            ->take($count)
            ->map(function ($pair, $i) use ($byName, $topics) {
                [$candidate, $item] = $pair;
                $topic = $byName->get(mb_strtolower(trim((string) ($candidate['topic'] ?? '')))) ?? $topics[$i % $topics->count()];

                return [
                    'question_id' => null,
                    'topic_id' => $topic->id,
                    'type' => $item['type'],
                    'difficulty' => $item['difficulty'],
                    'content' => $this->sanitizer->clean('<p>'.e($item['content']).'</p>'),
                    'options' => isset($item['options'])
                        ? collect($item['options'])->map(fn ($o, $k) => [
                            'id' => $k + 1,
                            'content' => e($o),
                            'is_correct' => in_array($k, $item['correct'], true),
                        ])->all()
                        : null,
                    'correct_answer' => match ($item['type']) {
                        Question::TYPE_TRUE_FALSE => ['value' => $item['correct']],
                        Question::TYPE_FILL_BLANK => ['blanks' => $item['blanks']],
                        Question::TYPE_SHORT_ANSWER => ['accepted' => $item['accepted']],
                        default => null,
                    },
                    'explanation' => $item['explanation'] !== '' ? '<p>'.e($item['explanation']).'</p>' : null,
                    'points' => 1,
                ];
            });
    }

    // --- Nộp bài & phân tích ---------------------------------------------------------------------

    /**
     * @param  array<int, mixed>  $answers  placement_test_question_id => giá trị
     * @param  array<int, int>  $timeSpent
     */
    public function submit(PlacementTest $test, array $answers, array $timeSpent = [], bool $auto = false): PlacementTest
    {
        $graded = DB::transaction(function () use ($test, $answers, $timeSpent, $auto) {
            /** @var PlacementTest $locked */
            $locked = PlacementTest::with('questions.topic', 'user')->whereKey($test->id)->lockForUpdate()->firstOrFail();

            if (! $locked->isInProgress()) {
                return null;
            }

            // Nộp quá ân hạn (vd tab bị treo) → không nhận đáp án gửi muộn.
            if (! $auto && $locked->isPastDeadline()) {
                $auto = true;
                $answers = [];
            }

            $score = 0.0;
            $max = 0.0;
            $correct = 0;
            $earned = 0.0;
            $possible = 0.0;
            $seconds = 0;
            $byTopic = [];
            $bankTopicIds = [];

            foreach ($locked->questions as $pq) {
                $value = $answers[$pq->id] ?? null;
                $result = $this->grading->grade($pq->toQuestion(), $value, (float) $pq->points);
                $time = min(1800, max(0, (int) ($timeSpent[$pq->id] ?? 0)));

                PlacementTestAnswer::create([
                    'placement_test_id' => $locked->id,
                    'placement_test_question_id' => $pq->id,
                    'answer' => ['value' => $value],
                    'is_correct' => $result->isCorrect === true,
                    'score' => $result->score,
                    'time_spent_seconds' => $time,
                ]);

                $score += $result->score;
                $max += $result->maxScore;
                $correct += $result->isCorrect === true ? 1 : 0;
                $seconds += $time;

                // Mức độ hiểu: đúng câu khó đáng giá hơn đúng câu dễ.
                $weight = MasteryService::WEIGHTS[$pq->difficulty] ?? 1.0;
                $possible += $weight;
                $earned += $result->isCorrect === true ? $weight : 0;

                if ($pq->topic_id) {
                    $byTopic[$pq->topic_id] ??= ['name' => $pq->topic?->name, 'correct' => 0, 'total' => 0];
                    $byTopic[$pq->topic_id]['total']++;
                    $byTopic[$pq->topic_id]['correct'] += $result->isCorrect === true ? 1 : 0;
                }

                // Câu từ ngân hàng ghi vào lịch sử để mastery / AI Tutor biết ngay từ ngày đầu.
                if ($pq->question_id) {
                    QuestionAttempt::create([
                        'user_id' => $locked->user_id,
                        'question_id' => $pq->question_id,
                        'topic_id' => $pq->topic_id,
                        'context' => QuestionAttempt::CONTEXT_PLACEMENT,
                        'context_id' => $locked->id,
                        'difficulty' => $pq->difficulty,
                        'answer' => ['value' => $value],
                        'is_correct' => $result->isCorrect,
                        'score' => $result->score,
                        'time_spent_seconds' => $time,
                    ]);
                    $bankTopicIds[] = $pq->topic_id;
                }
            }

            $score10 = $max > 0 ? round($score / $max * 10, 2) : 0.0;
            $count = $locked->questions->count();

            $weakTopics = collect($byTopic)
                ->map(fn ($t, $id) => ['topic_id' => (int) $id, 'name' => $t['name'], 'percent' => (int) round($t['correct'] / $t['total'] * 100)])
                ->filter(fn ($t) => $t['percent'] < self::WEAK_TOPIC_PERCENT)
                ->sortBy('percent')
                ->values()
                ->all();

            $locked->update([
                'status' => PlacementTest::STATUS_GRADED,
                'submitted_at' => $auto ? $locked->expires_at : now(),
                'auto_submitted' => $auto,
                'correct_count' => $correct,
                'score' => $score10,
                'level_result' => StudentProfile::classifyLevel($score10),
                'understanding_percent' => $possible > 0 ? (int) round($earned / $possible * 100) : 0,
                // Nộp tự động không có số liệu thời gian thật → để trống, không bịa.
                'avg_seconds_per_question' => ! $auto && $seconds > 0 && $count > 0 ? (int) round($seconds / $count) : null,
                'weak_topics' => $weakTopics,
            ]);

            if ($bankTopicIds !== []) {
                $this->mastery->recalculateForTopics($locked->user, array_values(array_unique($bankTopicIds)));
            }

            return $locked;
        });

        if (! $graded) {
            return $test->refresh();
        }

        $this->paths->generate($graded->user, $graded);
        $graded->update(['analysis' => $this->analyze($graded)]);

        return $graded->refresh();
    }

    /** Chốt bài đầu vào bỏ dở quá giờ (chạy cùng lệnh exams:finalize-expired). */
    public function finalizeExpired(): int
    {
        $count = 0;

        PlacementTest::query()
            ->where('status', PlacementTest::STATUS_IN_PROGRESS)
            ->where('expires_at', '<', now()->subSeconds(PlacementTest::GRACE_SECONDS))
            ->chunkById(100, function ($tests) use (&$count) {
                foreach ($tests as $test) {
                    $this->submit($test, [], [], auto: true);
                    $count++;
                }
            });

        return $count;
    }

    /** Nhận xét bằng AI. Lỗi AI không chặn kết quả — học sinh vẫn có điểm và lộ trình. */
    private function analyze(PlacementTest $test): ?string
    {
        $student = $test->user->loadMissing('studentProfile.grade');
        $weak = collect($test->weak_topics)->pluck('name')->implode(', ') ?: 'không có';

        try {
            $response = $this->provider->complete(new AiRequest(
                messages: [
                    ['role' => 'system', 'content' => $this->prompts->system($student, 'placement_analysis')],
                    ['role' => 'user', 'content' => "Kết quả kiểm tra đầu vào của em: {$test->score}/10 (xếp loại {$test->levelLabel()}), "
                        ."đúng {$test->correct_count}/{$test->total_questions} câu, mức độ hiểu {$test->understanding_percent}%"
                        .($test->avg_seconds_per_question ? ", trung bình {$test->avg_seconds_per_question} giây/câu" : '')
                        .". Chủ đề còn yếu: {$weak}."],
                ],
                task: 'placement_analysis',
                maxTokens: 350,
            ));
        } catch (AiProviderException) {
            $this->usage->recordFailure($student, 'placement_analysis');

            return null;
        }

        $this->usage->record($student, 'placement_analysis', $response);

        return $response->content;
    }
}
