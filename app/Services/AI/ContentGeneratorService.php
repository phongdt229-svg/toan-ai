<?php

namespace App\Services\AI;

use App\Jobs\GenerateAiContent;
use App\Models\AiGenerationDraft;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Models\Question;
use App\Models\Topic;
use App\Models\User;
use App\Services\AI\Contracts\AiProviderInterface;
use App\Services\Teaching\ExamBuilderService;
use App\Services\Teaching\QuestionService;
use App\Support\HtmlSanitizer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * AI soạn nội dung cho giáo viên (§12). Nguyên tắc cứng: AI KHÔNG tự xuất bản —
 * mọi thứ vào ai_generation_drafts, giáo viên duyệt từng mục mới thành câu hỏi / bài học thật.
 */
class ContentGeneratorService
{
    private const AUTO_TYPES = [
        Question::TYPE_SINGLE_CHOICE, Question::TYPE_MULTIPLE_CHOICE, Question::TYPE_TRUE_FALSE,
        Question::TYPE_FILL_BLANK, Question::TYPE_SHORT_ANSWER, Question::TYPE_ESSAY,
    ];

    public function __construct(
        private readonly AiProviderInterface $provider,
        private readonly AiUsageGuard $usage,
        private readonly QuestionService $questions,
        private readonly ExamBuilderService $examBuilder,
        private readonly HtmlSanitizer $sanitizer,
    ) {}

    // --- Tạo yêu cầu ---------------------------------------------------------------

    /**
     * @param  array{grade_id: int, topic_id: int, count: int, easy: int, medium: int, hard: int, types: array<int, string>, notes?: ?string}  $input
     */
    public function queueQuestions(User $teacher, array $input): AiGenerationDraft
    {
        $this->usage->ensureAllowed($teacher);

        $draft = AiGenerationDraft::create([
            'user_id' => $teacher->id,
            'type' => AiGenerationDraft::TYPE_QUESTIONS,
            'status' => AiGenerationDraft::STATUS_PENDING,
            'input' => $input,
        ]);

        GenerateAiContent::dispatch($draft->id);

        return $draft;
    }

    /** @param  array{grade_id: int, topic_id: int, title: string, difficulty: string, notes?: ?string}  $input */
    public function queueLesson(User $teacher, array $input): AiGenerationDraft
    {
        $this->usage->ensureAllowed($teacher);

        $draft = AiGenerationDraft::create([
            'user_id' => $teacher->id,
            'type' => AiGenerationDraft::TYPE_LESSON,
            'status' => AiGenerationDraft::STATUS_PENDING,
            'input' => $input,
        ]);

        GenerateAiContent::dispatch($draft->id);

        return $draft;
    }

    // --- Chạy trong job ---------------------------------------------------------------

    public function generate(AiGenerationDraft $draft): void
    {
        $draft->update(['status' => AiGenerationDraft::STATUS_PROCESSING, 'error' => null]);
        $teacher = $draft->loadMissing('user')->user;

        try {
            $output = $draft->type === AiGenerationDraft::TYPE_QUESTIONS
                ? $this->generateQuestions($teacher, $draft->input)
                : $this->generateLesson($teacher, $draft->input);

            $draft->update(['status' => AiGenerationDraft::STATUS_READY, 'output' => $output]);
        } catch (AiProviderException|AiQuotaExceededException $e) {
            $draft->update(['status' => AiGenerationDraft::STATUS_FAILED, 'error' => $e->getMessage()]);
        } catch (Throwable $e) {
            report($e);
            $draft->update(['status' => AiGenerationDraft::STATUS_FAILED, 'error' => 'Có lỗi khi tạo nội dung, hãy thử lại.']);
        }
    }

    /** @param  array<string, mixed>  $input */
    private function generateQuestions(User $teacher, array $input): array
    {
        $plan = array_filter($this->examBuilder->quotas((int) $input['count'], [
            'easy' => (int) $input['easy'], 'medium' => (int) $input['medium'], 'hard' => (int) $input['hard'],
        ]));

        $response = $this->call($teacher, 'generate_questions', [
            ['role' => 'system', 'content' => $this->teacherSystem($input)],
            ['role' => 'user', 'content' => $this->questionsPrompt($input, $plan)],
        ], ['plan' => $plan]);

        $raw = $response->json()['questions'] ?? [];
        $items = [];
        $dropped = 0;

        foreach (is_array($raw) ? $raw : [] as $candidate) {
            $item = $this->normalizeQuestion($candidate, $input['types']);

            if ($item) {
                $items[] = [...$item, 'status' => 'pending'];
            } else {
                $dropped++;
            }
        }

        if ($items === []) {
            throw new AiProviderException('AI không tạo được câu hỏi hợp lệ nào. Hãy thử lại hoặc đổi yêu cầu.', 'invalid_json');
        }

        return ['items' => $items, 'dropped' => $dropped, 'plan' => $plan];
    }

    /** @param  array<string, mixed>  $input */
    private function generateLesson(User $teacher, array $input): array
    {
        $response = $this->call($teacher, 'generate_lesson', [
            ['role' => 'system', 'content' => $this->teacherSystem($input)],
            ['role' => 'user', 'content' => "Soạn bài học \"{$input['title']}\", độ khó ".Question::DIFFICULTIES[$input['difficulty']].'.'
                .(! empty($input['notes']) ? "\nYêu cầu thêm của giáo viên: {$input['notes']}" : '')
                ."\n\nTrả về JSON: {\"sections\": [{\"type\": string, \"title\": string, \"content\": string (HTML đơn giản: <p>, <ul>, <li>, <strong>)}]}."
                ."\ntype chỉ dùng: ".implode(', ', array_keys(LessonSection::TYPES)).'.'
                ."\nThứ tự nên là: theory, example, insight, formula, common_mistake, quiz, practice."],
        ]);

        $sections = [];
        foreach ((array) ($response->json()['sections'] ?? []) as $s) {
            if (! is_array($s) || ! isset(LessonSection::TYPES[$s['type'] ?? '']) || blank($s['content'] ?? null)) {
                continue;
            }

            $sections[] = [
                'type' => $s['type'],
                'title' => mb_substr(strip_tags((string) ($s['title'] ?? '')), 0, 191),
                // Lọc ngay khi nhận — HTML từ model là dữ liệu không tin cậy.
                'content' => $this->sanitizer->clean((string) $s['content']),
            ];
        }

        if ($sections === []) {
            throw new AiProviderException('AI không tạo được nội dung bài học hợp lệ. Hãy thử lại.', 'invalid_json');
        }

        return ['sections' => $sections, 'status' => 'pending'];
    }

    // --- Giáo viên duyệt ----------------------------------------------------------------

    /** "Chấp nhận" = giáo viên đã duyệt → tạo câu hỏi thật, xuất bản luôn. */
    public function acceptQuestion(AiGenerationDraft $draft, int $index, User $teacher): Question
    {
        return DB::transaction(function () use ($draft, $index, $teacher) {
            $draft = AiGenerationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();
            $item = $this->pendingItem($draft, $index);

            $question = $this->questions->create([
                ...$this->toQuestionForm($item, $draft->input),
                'status' => 'published',
                'source' => 'ai',
            ], $teacher);

            $this->updateItem($draft, $index, ['status' => 'accepted', 'question_id' => $question->id]);

            return $question;
        });
    }

    public function rejectQuestion(AiGenerationDraft $draft, int $index): void
    {
        $this->pendingItem($draft, $index);
        $this->updateItem($draft, $index, ['status' => 'rejected']);
    }

    /** "Tạo lại" một câu: cùng loại, cùng độ khó, nội dung khác. */
    public function regenerateQuestion(AiGenerationDraft $draft, int $index, User $teacher): void
    {
        $item = $this->pendingItem($draft, $index);

        $response = $this->call($teacher, 'generate_questions', [
            ['role' => 'system', 'content' => $this->teacherSystem($draft->input)],
            ['role' => 'user', 'content' => $this->questionsPrompt(
                [...$draft->input, 'types' => [$item['type']]],
                [$item['difficulty'] => 1],
            )."\nKHÁC với câu sau: ".$item['content']],
        ], ['plan' => [$item['difficulty'] => 1]]);

        $new = $this->normalizeQuestion(($response->json()['questions'] ?? [])[0] ?? null, [$item['type']]);

        if (! $new) {
            throw new AiProviderException('AI chưa tạo được câu thay thế hợp lệ, thử lại nhé.', 'invalid_json');
        }

        $this->updateItem($draft, $index, [...$new, 'status' => 'pending'], replace: true);
    }

    /** Giáo viên sửa rồi lưu từ form thường → đánh dấu mục nháp đã dùng, tránh chấp nhận trùng. */
    public function linkEditedQuestion(AiGenerationDraft $draft, int $index, Question $question): void
    {
        if (($draft->items()[$index]['status'] ?? null) === 'pending') {
            $this->updateItem($draft, $index, ['status' => 'accepted', 'question_id' => $question->id]);
        }
    }

    /** Dữ liệu đổ sẵn vào form "Tạo câu hỏi" khi giáo viên chọn "Sửa". */
    public function questionFormData(AiGenerationDraft $draft, int $index): array
    {
        return [...$this->toQuestionForm($this->pendingItem($draft, $index), $draft->input), 'source' => 'ai'];
    }

    public function createLessonFromDraft(AiGenerationDraft $draft, User $teacher): Lesson
    {
        return DB::transaction(function () use ($draft, $teacher) {
            $draft = AiGenerationDraft::whereKey($draft->id)->lockForUpdate()->firstOrFail();

            if (($draft->output['status'] ?? null) !== 'pending') {
                throw new RuntimeException('Bản nháp này đã được dùng để tạo bài học.');
            }

            $title = $draft->input['title'];
            $slug = Str::slug($title) ?: 'bai-hoc';
            $base = $slug;
            for ($i = 2; Lesson::withTrashed()->where('slug', $slug)->exists(); $i++) {
                $slug = "{$base}-{$i}";
            }

            // Bài tạo ra ở trạng thái NHÁP — giáo viên đọc lại, sửa, rồi tự bấm xuất bản.
            $lesson = Lesson::create([
                'topic_id' => $draft->input['topic_id'],
                'title' => $title,
                'slug' => $slug,
                'difficulty' => $draft->input['difficulty'],
                'estimated_minutes' => 15,
                'access_level' => Lesson::ACCESS_FREE,
                'status' => Lesson::STATUS_DRAFT,
                'created_by' => $teacher->id,
            ]);

            foreach ($draft->output['sections'] as $i => $section) {
                $lesson->sections()->create([...$section, 'sort_order' => $i + 1]);
            }

            $draft->update(['output' => [...$draft->output, 'status' => 'accepted', 'lesson_id' => $lesson->id]]);

            return $lesson;
        });
    }

    /** Viết lại / tóm tắt một đoạn nội dung bài học (§12). Trả HTML đã lọc, chưa lưu. */
    public function rewrite(User $teacher, string $content, string $mode): string
    {
        $this->usage->ensureAllowed($teacher);

        $instruction = $mode === 'summarize'
            ? 'Tóm tắt nội dung sau thành các ý chính ngắn gọn cho học sinh.'
            : 'Viết lại nội dung sau cho dễ hiểu hơn với học sinh, giữ nguyên kiến thức và công thức.';

        $response = $this->call($teacher, 'rewrite', [
            ['role' => 'system', 'content' => 'Bạn là giáo viên Toán Việt Nam biên soạn học liệu. Trả về HTML đơn giản (<p>, <ul>, <li>, <strong>), công thức trong $...$. Không thêm lời dẫn. Nếu nội dung được đưa không liên quan tới Toán học, chỉ trả về đúng "<p>Nội dung này không thuộc phạm vi Toán học.</p>", không viết lại gì thêm.'],
            ['role' => 'user', 'content' => "{$instruction}\n\n{$content}"],
        ]);

        return (string) $this->sanitizer->clean($response->content);
    }

    // --- Nội bộ ------------------------------------------------------------------------

    /**
     * Chuẩn hoá một câu AI sinh. Sai cấu trúc → null (bỏ), không cố đoán ý model.
     *
     * @param  array<int, string>  $allowedTypes
     */
    public function normalizeQuestion(mixed $q, array $allowedTypes): ?array
    {
        if (! is_array($q)) {
            return null;
        }

        $type = $q['type'] ?? null;
        $content = trim((string) ($q['content'] ?? ''));
        $difficulty = $q['difficulty'] ?? 'medium';

        if (! in_array($type, $allowedTypes, true) || $content === '' || ! isset(Question::DIFFICULTIES[$difficulty])) {
            return null;
        }

        $item = [
            'type' => $type,
            'difficulty' => $difficulty,
            'content' => mb_substr($content, 0, 5000),
            'explanation' => mb_substr(trim((string) ($q['explanation'] ?? '')), 0, 5000),
        ];

        switch ($type) {
            case Question::TYPE_SINGLE_CHOICE:
            case Question::TYPE_MULTIPLE_CHOICE:
                $options = array_values(array_filter(array_map(fn ($o) => trim((string) $o), (array) ($q['options'] ?? [])), 'strlen'));
                $correct = array_values(array_unique(array_filter((array) ($q['correct'] ?? []), fn ($i) => is_int($i) && isset($options[$i]))));

                if (count($options) < 2 || count($options) > 6 || $correct === []
                    || ($type === Question::TYPE_SINGLE_CHOICE && count($correct) !== 1)) {
                    return null;
                }

                return [...$item, 'options' => $options, 'correct' => $correct];

            case Question::TYPE_TRUE_FALSE:
                return is_bool($q['correct'] ?? null) ? [...$item, 'correct' => $q['correct']] : null;

            case Question::TYPE_FILL_BLANK:
                $blanks = array_values(array_filter(array_map(
                    fn ($b) => array_values(array_filter(array_map(fn ($v) => trim((string) $v), (array) $b), 'strlen')),
                    (array) ($q['blanks'] ?? []),
                )));

                return $blanks ? [...$item, 'blanks' => $blanks] : null;

            case Question::TYPE_SHORT_ANSWER:
                $accepted = array_values(array_filter(array_map(fn ($v) => trim((string) $v), (array) ($q['accepted'] ?? [])), 'strlen'));

                return $accepted ? [...$item, 'accepted' => $accepted] : null;

            case Question::TYPE_ESSAY:
                return $item;
        }

        return null;
    }

    /** Mục nháp → mảng đúng định dạng QuestionRequest / QuestionService. */
    private function toQuestionForm(array $item, array $input): array
    {
        return [
            'grade_id' => $input['grade_id'],
            'topic_id' => $input['topic_id'],
            'type' => $item['type'],
            'content' => '<p>'.$item['content'].'</p>',
            'explanation' => $item['explanation'] !== '' ? '<p>'.$item['explanation'].'</p>' : null,
            'difficulty' => $item['difficulty'],
            'points' => 1,
            'options' => array_map(fn ($o) => ['content' => $o], $item['options'] ?? []),
            'correct_options' => array_map('strval', $item['correct'] ?? []),
            'true_false_value' => isset($item['correct']) && is_bool($item['correct']) ? ($item['correct'] ? '1' : '0') : null,
            'blanks' => array_map(fn ($alts) => implode(' | ', $alts), $item['blanks'] ?? []),
            'accepted' => implode(' | ', $item['accepted'] ?? []),
        ];
    }

    private function pendingItem(AiGenerationDraft $draft, int $index): array
    {
        $item = $draft->items()[$index] ?? null;

        if (! $item || ($item['status'] ?? null) !== 'pending') {
            throw new RuntimeException('Mục này đã được xử lý hoặc không tồn tại.');
        }

        return $item;
    }

    private function updateItem(AiGenerationDraft $draft, int $index, array $values, bool $replace = false): void
    {
        $output = $draft->output;
        $output['items'][$index] = $replace ? $values : [...$output['items'][$index], ...$values];
        $draft->update(['output' => $output]);
    }

    /** @param  array<string, mixed>  $input */
    private function teacherSystem(array $input): string
    {
        $grade = Grade::find($input['grade_id']);
        $topic = Topic::with('chapter')->find($input['topic_id']);

        return implode("\n", [
            'Bạn là giáo viên Toán giàu kinh nghiệm, biên soạn học liệu theo chương trình phổ thông Việt Nam.',
            "Lớp: {$grade?->name}. Chương: {$topic?->chapter?->name}. Chủ đề: {$topic?->name}.",
            'Quy tắc:',
            '- Viết tiếng Việt chuẩn, đúng kiến thức, phù hợp trình độ lớp.',
            '- Công thức dùng LaTeX trong $...$.',
            '- Chỉ trả về JSON hợp lệ đúng cấu trúc được yêu cầu, không thêm lời dẫn.',
            '- Đáp án phải chính xác; không chắc chắn thì không tạo câu đó.',
        ]);
    }

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, int>  $plan  độ khó => số câu
     */
    private function questionsPrompt(array $input, array $plan): string
    {
        $labels = ['easy' => 'Dễ', 'medium' => 'Trung bình', 'hard' => 'Khó'];
        $planText = collect($plan)->map(fn ($n, $d) => "{$n} câu {$labels[$d]}")->implode(', ');

        return "Tạo {$planText}. Chỉ dùng các loại: ".implode(', ', $input['types']).'.'
            .(! empty($input['notes']) ? "\nYêu cầu thêm: {$input['notes']}" : '')
            ."\n\nTrả về JSON: {\"questions\": [ {\"type\", \"difficulty\" (easy|medium|hard), \"content\", \"explanation\", ...} ]}"
            ."\n- single_choice / multiple_choice: \"options\": [chuỗi], \"correct\": [chỉ số bắt đầu từ 0] (single_choice đúng 1 chỉ số)"
            ."\n- true_false: \"correct\": true|false"
            ."\n- fill_blank: \"blanks\": [[các cách viết đáp án chỗ trống 1], [chỗ trống 2]...]"
            ."\n- short_answer: \"accepted\": [các cách viết đáp án được chấp nhận]"
            ."\n- essay: không cần đáp án";
    }

    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  array<string, mixed>  $meta
     */
    private function call(User $teacher, string $task, array $messages, array $meta = []): AiResponse
    {
        try {
            $response = $this->provider->complete(new AiRequest(
                messages: $messages,
                task: $task,
                maxTokens: config("ai.max_output_tokens.{$task}", 2000),
                temperature: 0.6,
                json: $task !== 'rewrite',
                meta: $meta,
            ));
        } catch (AiProviderException $e) {
            $this->usage->recordFailure($teacher, $task);

            throw $e;
        }

        $this->usage->record($teacher, $task, $response);

        return $response;
    }

    /** @return array<int, string> */
    public static function allowedTypes(): array
    {
        return self::AUTO_TYPES;
    }
}
