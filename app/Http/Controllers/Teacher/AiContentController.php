<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\AiGenerationDraft;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Lesson;
use App\Models\LessonSection;
use App\Models\Question;
use App\Models\Topic;
use App\Services\AI\AiProviderException;
use App\Services\AI\AiQuotaExceededException;
use App\Services\AI\AiUsageGuard;
use App\Services\AI\ContentGeneratorService;
use App\Services\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

/**
 * AI hỗ trợ giáo viên soạn nội dung (§12). Mọi output là NHÁP — giáo viên duyệt từng mục.
 */
class AiContentController extends Controller
{
    public function __construct(
        private readonly ContentGeneratorService $generator,
        private readonly AiUsageGuard $usage,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->ensureCanGenerate($request);

        return view('teacher.ai.index', [
            'drafts' => AiGenerationDraft::where('user_id', $request->user()->id)->latest()->paginate(15),
            'usage' => $this->usage->status($request->user()),
            'grades' => $this->gradeTree(),
            'types' => ContentGeneratorService::allowedTypes(),
        ]);
    }

    public function storeQuestions(Request $request): RedirectResponse
    {
        $this->ensureCanGenerate($request);

        $data = $request->validate([
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'count' => ['required', 'integer', 'min:1', 'max:20'],
            'easy' => ['required', 'integer', 'min:0', 'max:100'],
            'medium' => ['required', 'integer', 'min:0', 'max:100'],
            'hard' => ['required', 'integer', 'min:0', 'max:100'],
            'types' => ['required', 'array', 'min:1'],
            'types.*' => [Rule::in(ContentGeneratorService::allowedTypes())],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['topic_id' => 'chủ đề', 'count' => 'số câu', 'types' => 'loại câu hỏi']);

        if ($data['easy'] + $data['medium'] + $data['hard'] !== 100) {
            return back()->withInput()->with('error', 'Tổng tỉ lệ Dễ + Trung bình + Khó phải bằng 100%.');
        }

        $this->ensureTopicInGrade($data);

        return $this->queue(fn () => $this->generator->queueQuestions($request->user(), $data));
    }

    public function storeLesson(Request $request): RedirectResponse
    {
        $this->ensureCanGenerate($request);

        $data = $request->validate([
            'grade_id' => ['required', 'integer', 'exists:grades,id'],
            'topic_id' => ['required', 'integer', 'exists:topics,id'],
            'title' => ['required', 'string', 'max:191'],
            'difficulty' => ['required', Rule::in(array_keys(Question::DIFFICULTIES))],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['topic_id' => 'chủ đề', 'title' => 'tên bài học']);

        $this->ensureTopicInGrade($data);

        return $this->queue(fn () => $this->generator->queueLesson($request->user(), $data));
    }

    public function show(Request $request, AiGenerationDraft $draft): View
    {
        $this->ensureOwner($request, $draft);

        return view('teacher.ai.show', [
            'draft' => $draft,
            'topic' => Topic::with('chapter.subject.grade')->find($draft->input['topic_id'] ?? null),
            'sectionTypes' => LessonSection::TYPES,
            // Route bài học bind theo slug, nên cần model chứ không chỉ id lưu trong nháp.
            'createdExam' => isset($draft->output['exam_id']) ? Exam::find($draft->output['exam_id']) : null,
            'createdLesson' => isset($draft->output['lesson_id']) ? Lesson::find($draft->output['lesson_id']) : null,
        ]);
    }

    public function accept(Request $request, AiGenerationDraft $draft, int $index): RedirectResponse
    {
        $this->ensureOwner($request, $draft);

        return $this->act(function () use ($request, $draft, $index) {
            $question = $this->generator->acceptQuestion($draft, $index, $request->user());
            $this->audit->log('ai.question_accepted', $question, null, ['draft_id' => $draft->id]);

            return 'Đã thêm câu hỏi vào ngân hàng.';
        });
    }

    public function reject(Request $request, AiGenerationDraft $draft, int $index): RedirectResponse
    {
        $this->ensureOwner($request, $draft);

        return $this->act(function () use ($draft, $index) {
            $this->generator->rejectQuestion($draft, $index);

            return 'Đã bỏ câu này.';
        });
    }

    public function regenerate(Request $request, AiGenerationDraft $draft, int $index): RedirectResponse
    {
        $this->ensureOwner($request, $draft);

        return $this->act(function () use ($request, $draft, $index) {
            $this->generator->regenerateQuestion($draft, $index, $request->user());

            return 'Đã tạo lại câu hỏi.';
        });
    }

    /** "Sửa": mở form tạo câu hỏi với dữ liệu nháp đổ sẵn. */
    public function edit(Request $request, AiGenerationDraft $draft, int $index): RedirectResponse
    {
        $this->ensureOwner($request, $draft);

        try {
            $data = $this->generator->questionFormData($draft, $index);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('teacher.questions.create')
            ->withInput([...$data, 'ai_draft_id' => $draft->id, 'ai_item_index' => $index, 'status' => 'published']);
    }

    public function createLesson(Request $request, AiGenerationDraft $draft): RedirectResponse
    {
        $this->ensureOwner($request, $draft);

        try {
            $lesson = $this->generator->createLessonFromDraft($draft, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('ai.lesson_created', $lesson, null, ['draft_id' => $draft->id]);

        return redirect()->route('teacher.lessons.edit', $lesson)
            ->with('status', 'Đã tạo bài học NHÁP từ AI. Đọc lại, chỉnh sửa rồi mới xuất bản.');
    }

    public function createExam(Request $request, AiGenerationDraft $draft): RedirectResponse
    {
        $this->ensureOwner($request, $draft);
        $this->authorize('create', Exam::class);

        try {
            $exam = $this->generator->createExamFromDraft($draft, $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log('exam.created', $exam, null, ['title' => $exam->title, 'ai_draft_id' => $draft->id]);

        return redirect()->route('teacher.exams.edit', $exam)
            ->with('status', 'Đã tạo đề NHÁP từ các câu bạn đã duyệt. Chỉnh thời gian, điểm rồi xuất bản khi sẵn sàng.');
    }

    /** Viết lại / tóm tắt một đoạn trong trình soạn bài học. */
    public function rewrite(Request $request): JsonResponse
    {
        $this->ensureCanGenerate($request);

        $data = $request->validate([
            'content' => ['required', 'string', 'max:10000'],
            'mode' => ['required', Rule::in(['simplify', 'summarize'])],
        ]);

        try {
            $html = $this->generator->rewrite($request->user(), $data['content'], $data['mode']);
        } catch (AiQuotaExceededException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 429);
        } catch (AiProviderException $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 503);
        }

        return response()->json(['success' => true, 'data' => ['html' => $html]]);
    }

    // ------------------------------------------------------------------------------------

    private function queue(\Closure $create): RedirectResponse
    {
        try {
            $draft = $create();
        } catch (AiQuotaExceededException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('teacher.ai.show', $draft)
            ->with('status', 'Đã gửi yêu cầu. AI đang soạn — trang sẽ tự cập nhật.');
    }

    private function act(\Closure $action): RedirectResponse
    {
        try {
            return back()->with('status', $action());
        } catch (RuntimeException $e) {
            // Gồm cả AiProviderException (kế thừa RuntimeException).
            return back()->with('error', $e->getMessage());
        }
    }

    private function ensureCanGenerate(Request $request): void
    {
        abort_unless($request->user()->hasPermission('ai.generate_content') || $request->user()->isAdmin(), 403);
    }

    private function ensureOwner(Request $request, AiGenerationDraft $draft): void
    {
        abort_unless($draft->user_id === $request->user()->id, 403);
    }

    /** @param  array<string, mixed>  $data */
    private function ensureTopicInGrade(array $data): void
    {
        $ok = Topic::whereKey($data['topic_id'])
            ->whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $data['grade_id']))
            ->exists();

        abort_unless($ok, 422, 'Chủ đề không thuộc lớp đã chọn.');
    }

    private function gradeTree()
    {
        return Grade::query()->active()->ordered()
            ->with(['subjects.chapters.topics' => fn ($q) => $q->active()->ordered()])
            ->get();
    }
}
