<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\QaQuestionRequest;
use App\Models\Chapter;
use App\Models\QaAnswer;
use App\Models\QaQuestion;
use App\Models\Topic;
use App\Services\Learning\QaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

/** Mục Hỏi đáp: đặt câu hỏi, trả lời, chọn lời giải, báo xấu. */
class QaController extends Controller
{
    public function __construct(private readonly QaService $qa) {}

    public function index(Request $request): View
    {
        $filter = $request->string('loc')->toString();
        $search = trim($request->string('q')->toString());

        $questions = QaQuestion::query()
            ->visible()
            ->with('user:id,name', 'topic:id,name,chapter_id', 'topic.chapter:id,name')
            ->when($filter === 'cua-toi', fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($filter === 'chua-tra-loi', fn ($q) => $q->where('answers_count', 0))
            ->when($filter === 'da-giai', fn ($q) => $q->where('status', QaQuestion::STATUS_RESOLVED))
            ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                ->where('title', 'like', "%{$search}%")
                ->orWhere('body', 'like', "%{$search}%")))
            ->latest('id')
            ->paginate(config('site.per_page'))
            ->withQueryString();

        return view('student.qa.index', [
            'questions' => $questions,
            'filter' => $filter,
            'search' => $search,
        ]);
    }

    public function create(Request $request): View
    {
        $this->authorize('create', QaQuestion::class);

        return view('student.qa.create', ['chapters' => $this->topicOptions($request)]);
    }

    public function store(QaQuestionRequest $request): RedirectResponse
    {
        $this->authorize('create', QaQuestion::class);

        $question = $this->qa->ask(
            $request->user(),
            Topic::findOrFail($request->validated()['topic_id']),
            $request->validated()['title'],
            $request->validated()['body'],
        );

        return redirect()->route('student.qa.show', $question)
            ->with('status', 'Đã đăng câu hỏi. Có người trả lời là em nhận được thông báo.');
    }

    public function show(Request $request, QaQuestion $question): View
    {
        $this->authorize('view', $question);

        $question->load('user:id,name', 'topic:id,name,chapter_id', 'topic.chapter:id,name');

        // Câu trả lời đang ẩn vẫn hiện cho người viết và người kiểm duyệt, kèm nhãn rõ ràng.
        $answers = $question->answers()
            ->with('user:id,name')
            ->when(
                ! $request->user()->can('moderate', QaQuestion::class),
                fn ($q) => $q->where(fn ($w) => $w->visible()->orWhere('user_id', $request->user()->id)),
            )
            ->orderByRaw('CASE WHEN id = ? THEN 0 ELSE 1 END', [$question->best_answer_id ?? 0])
            ->oldest('id')
            ->get();

        return view('student.qa.show', ['question' => $question, 'answers' => $answers]);
    }

    public function answer(Request $request, QaQuestion $question): RedirectResponse
    {
        $this->authorize('answer', $question);

        $data = $request->validate(
            ['body' => ['required', 'string', 'min:10', 'max:5000']],
            [],
            ['body' => 'nội dung'],
        );

        try {
            $this->qa->answer($request->user(), $question, $data['body']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Đã gửi câu trả lời.');
    }

    public function accept(Request $request, QaQuestion $question, QaAnswer $answer): RedirectResponse
    {
        $this->authorize('accept', $question);

        try {
            $this->qa->acceptAnswer($question, $answer);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Đã chọn câu trả lời này là lời giải.');
    }

    public function report(Request $request, string $type, int $id): RedirectResponse
    {
        $target = $type === 'cau-hoi' ? QaQuestion::findOrFail($id) : QaAnswer::findOrFail($id);

        $this->authorize('report', $target);

        $hidden = $this->qa->report(
            $request->user(),
            $target,
            $request->string('reason')->toString() ?: null,
        );

        return back()->with('status', $hidden
            ? 'Cảm ơn em. Nội dung đã được ẩn để thầy cô xem lại.'
            : 'Cảm ơn em, thầy cô sẽ xem lại nội dung này.');
    }

    /** Giáo viên và quản trị ẩn / hiện lại nội dung. */
    public function moderate(Request $request, string $type, int $id): RedirectResponse
    {
        $this->authorize('moderate', QaQuestion::class);

        $target = $type === 'cau-hoi' ? QaQuestion::findOrFail($id) : QaAnswer::findOrFail($id);
        $hide = $request->boolean('hide');

        $this->qa->moderate($request->user(), $target, $hide);

        return back()->with('status', $hide ? 'Đã ẩn nội dung.' : 'Đã hiện lại nội dung.');
    }

    /** Chủ đề nhóm theo chương của đúng lớp học sinh đang học. */
    private function topicOptions(Request $request)
    {
        $gradeId = $request->user()->studentProfile?->grade_id;

        return Chapter::query()
            ->when($gradeId, fn ($q) => $q->whereHas('subject', fn ($s) => $s->where('grade_id', $gradeId)))
            ->with('topics:id,chapter_id,name')
            ->orderBy('sort_order')
            ->get()
            ->filter(fn (Chapter $c) => $c->topics->isNotEmpty());
    }
}
