<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\QuestionRequest;
use App\Models\AiGenerationDraft;
use App\Models\Grade;
use App\Models\Question;
use App\Services\AI\ContentGeneratorService;
use App\Services\AuditLogger;
use App\Services\Teaching\QuestionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class QuestionController extends Controller
{
    public function __construct(
        private readonly QuestionService $questions,
        private readonly AuditLogger $audit,
        private readonly ContentGeneratorService $generator,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Question::class);

        $list = Question::query()
            ->with('topic.chapter', 'grade')
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('created_by', $request->user()->id))
            ->when($request->filled('type'), fn ($q) => $q->where('type', $request->string('type')))
            ->when($request->filled('difficulty'), fn ($q) => $q->where('difficulty', $request->string('difficulty')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('content', 'like', '%'.$request->string('q').'%'))
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('teacher.questions.index', ['questions' => $list]);
    }

    public function create(): View
    {
        $this->authorize('create', Question::class);

        return view('teacher.questions.create', [
            'question' => new Question([
                'type' => Question::TYPE_SINGLE_CHOICE,
                'difficulty' => 'medium',
                'points' => 1,
                'status' => 'draft',
            ]),
            'grades' => $this->gradeTree(),
        ]);
    }

    public function store(QuestionRequest $request): RedirectResponse
    {
        $question = $this->questions->create($request->validated(), $request->user());

        $this->audit->log('question.created', $question, null, ['type' => $question->type, 'source' => $question->source]);

        if ($request->filled('ai_draft_id')) {
            $draft = AiGenerationDraft::where('user_id', $request->user()->id)->find($request->integer('ai_draft_id'));
            if ($draft) {
                $this->generator->linkEditedQuestion($draft, $request->integer('ai_item_index'), $question);

                return redirect()->route('teacher.ai.show', $draft)->with('status', 'Đã lưu câu hỏi đã sửa.');
            }
        }

        return redirect()
            ->route('teacher.questions.index')
            ->with('status', 'Đã tạo câu hỏi.');
    }

    public function edit(Question $question): View
    {
        $this->authorize('update', $question);

        return view('teacher.questions.edit', [
            'question' => $question->load('options'),
            'grades' => $this->gradeTree(),
        ]);
    }

    public function update(QuestionRequest $request, Question $question): RedirectResponse
    {
        $this->questions->update($question, $request->validated());

        $this->audit->log('question.updated', $question);

        return redirect()
            ->route('teacher.questions.index')
            ->with('status', 'Đã cập nhật câu hỏi.');
    }

    public function destroy(Question $question): RedirectResponse
    {
        $this->authorize('delete', $question);

        $this->audit->log('question.deleted', $question, ['type' => $question->type], null);
        $question->delete();

        return back()->with('status', 'Đã xoá câu hỏi.');
    }

    private function gradeTree()
    {
        return Grade::query()
            ->active()
            ->ordered()
            ->with(['subjects' => fn ($s) => $s->active()->ordered()
                ->with(['chapters' => fn ($c) => $c->active()->ordered()
                    ->with(['topics' => fn ($t) => $t->active()->ordered()])])])
            ->get();
    }
}
