<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Http\Requests\Student\StartPracticeRequest;
use App\Http\Requests\Student\SubmitPracticeRequest;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Topic;
use App\Services\Learning\MasteryService;
use App\Services\Learning\PracticeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PracticeController extends Controller
{
    /** Key phiên luyện tập — server giữ bộ câu hỏi, client không được đổi. */
    private const SESSION_KEY = 'practice.current';
    private const RESULT_KEY = 'practice.result';

    public function __construct(
        private readonly PracticeService $practice,
        private readonly MasteryService $mastery,
    ) {}

    /** Chọn chủ đề để luyện. */
    public function index(Request $request): View
    {
        $user = $request->user()->load('studentProfile.grade');

        $grade = $request->filled('grade')
            ? Grade::active()->where('slug', $request->string('grade'))->firstOrFail()
            : ($user->studentProfile?->grade ?? Grade::active()->ordered()->firstOrFail());

        // Chỉ hiện chủ đề thực sự có câu hỏi tự chấm được.
        $topics = Topic::query()
            ->whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $grade->id))
            ->with('chapter')
            ->withCount(['questions' => fn ($q) => $q->published()->where('type', '!=', Question::TYPE_ESSAY)])
            ->having('questions_count', '>', 0)
            ->ordered()
            ->get();

        return view('student.practice.index', [
            'grade' => $grade,
            'grades' => Grade::active()->ordered()->get(),
            'topics' => $topics,
            'weakTopics' => $this->mastery->weakTopics($user),
        ]);
    }

    public function start(StartPracticeRequest $request): RedirectResponse
    {
        $topic = Topic::findOrFail($request->integer('topic_id'));

        $questions = $this->practice->buildSet(
            $topic->id,
            $request->input('difficulty'),
            $request->integer('limit') ?: 10,
        );

        if ($questions->isEmpty()) {
            return back()->with('error', 'Chủ đề này chưa có câu hỏi phù hợp.');
        }

        $request->session()->put(self::SESSION_KEY, [
            'topic_id' => $topic->id,
            'question_ids' => $questions->pluck('id')->all(),
            'started_at' => now()->timestamp,
        ]);

        return redirect()->route('student.practice.show');
    }

    public function show(Request $request): View|RedirectResponse
    {
        $session = $request->session()->get(self::SESSION_KEY);

        if (! $session) {
            return redirect()->route('student.practice.index');
        }

        $questions = Question::whereIn('id', $session['question_ids'])
            ->with('options')
            ->get()
            // Giữ đúng thứ tự đã bốc, tránh câu nhảy chỗ khi tải lại trang.
            ->sortBy(fn ($q) => array_search($q->id, $session['question_ids'], true))
            ->values();

        return view('student.practice.show', [
            'topic' => Topic::with('chapter')->find($session['topic_id']),
            'questions' => $questions,
        ]);
    }

    public function submit(SubmitPracticeRequest $request): RedirectResponse
    {
        $session = $request->session()->get(self::SESSION_KEY);

        if (! $session) {
            return redirect()->route('student.practice.index')
                ->with('error', 'Phiên luyện tập đã hết hạn.');
        }

        $result = $this->practice->submit(
            $request->user(),
            $session['question_ids'],
            $request->input('answers', []),
            $request->input('time_spent', []),
        );

        $request->session()->forget(self::SESSION_KEY);
        $request->session()->put(self::RESULT_KEY, [
            'topic_id' => $session['topic_id'],
            'question_ids' => $session['question_ids'],
            'answers' => $request->input('answers', []),
            'score' => $result['score'],
            'max_score' => $result['max_score'],
            'correct' => $result['correct'],
            'percent' => $result['percent'],
        ]);

        return redirect()->route('student.practice.result');
    }

    /** Trang kết quả: hiện đúng/sai từng câu kèm giải thích (§15). */
    public function result(Request $request): View|RedirectResponse
    {
        $data = $request->session()->get(self::RESULT_KEY);

        if (! $data) {
            return redirect()->route('student.practice.index');
        }

        $questions = Question::whereIn('id', $data['question_ids'])
            ->with('options')
            ->get()
            ->sortBy(fn ($q) => array_search($q->id, $data['question_ids'], true))
            ->values();

        return view('student.practice.result', [
            'topic' => Topic::find($data['topic_id']),
            'questions' => $questions,
            'answers' => $data['answers'],
            'score' => $data['score'],
            'maxScore' => $data['max_score'],
            'correct' => $data['correct'],
            'percent' => $data['percent'],
        ]);
    }
}
