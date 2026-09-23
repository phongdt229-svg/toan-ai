<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\AddExamQuestionsRequest;
use App\Http\Requests\Teacher\AddRandomExamQuestionsRequest;
use App\Http\Requests\Teacher\ExamRequest;
use App\Http\Requests\Teacher\UpdateExamQuestionRequest;
use App\Models\Exam;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Topic;
use App\Services\AuditLogger;
use App\Services\Teaching\ExamBuilderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class ExamController extends Controller
{
    public function __construct(
        private readonly ExamBuilderService $builder,
        private readonly AuditLogger $audit,
    ) {}

    public function index(Request $request): View
    {
        $this->authorize('viewAny', Exam::class);

        $exams = Exam::query()
            ->with('grade')
            ->when(! $request->user()->isAdmin(), fn ($q) => $q->where('created_by', $request->user()->id))
            ->withCount('attempts')
            ->withCount(['attempts as pending_grading_count' => fn ($q) => $q->where('status', 'submitted')])
            ->latest('updated_at')
            ->paginate(20);

        return view('teacher.exams.index', ['exams' => $exams]);
    }

    public function create(): View
    {
        $this->authorize('create', Exam::class);

        return view('teacher.exams.create', [
            'exam' => new Exam([
                'type' => 'test',
                'duration_minutes' => 45,
                'difficulty' => 'mixed',
                'access_level' => 'free',
                'max_attempts' => 1,
                'shuffle_questions' => true,
                'shuffle_options' => true,
                'show_answers_after_submit' => true,
            ]),
            'grades' => Grade::active()->ordered()->get(),
        ]);
    }

    public function store(ExamRequest $request): RedirectResponse
    {
        $exam = $this->builder->create($request->validated(), $request->user());

        $this->audit->log('exam.created', $exam, null, ['title' => $exam->title]);

        return redirect()->route('teacher.exams.edit', $exam)
            ->with('status', 'Đã tạo đề. Thêm câu hỏi bên dưới.');
    }

    public function edit(Request $request, Exam $exam): View
    {
        $this->authorize('update', $exam);

        $exam->load('grade');

        return view('teacher.exams.edit', [
            'exam' => $exam,
            'grades' => Grade::active()->ordered()->get(),
            'examQuestions' => $exam->questions()->with('topic')->get(),
            'isLocked' => $exam->hasAttempts(),
            'topics' => Topic::query()
                ->whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $exam->grade_id))
                ->with('chapter')
                ->ordered()
                ->get(),
            'candidates' => $this->builder->candidates($exam, $request->user(), $request->only([
                'topic_id', 'difficulty', 'type',
            ])),
        ]);
    }

    public function update(ExamRequest $request, Exam $exam): RedirectResponse
    {
        $this->builder->update($exam, $request->validated());

        $this->audit->log('exam.updated', $exam);

        return back()->with('status', 'Đã lưu thông tin đề.');
    }

    public function togglePublish(Exam $exam): RedirectResponse
    {
        $this->authorize('update', $exam);

        try {
            $this->builder->togglePublish($exam);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $this->audit->log($exam->isPublished() ? 'exam.published' : 'exam.unpublished', $exam);

        return back()->with('status', $exam->isPublished() ? 'Đã xuất bản đề.' : 'Đã gỡ xuất bản đề.');
    }

    public function destroy(Exam $exam): RedirectResponse
    {
        $this->authorize('delete', $exam);

        $this->audit->log('exam.deleted', $exam, ['title' => $exam->title], null);
        // Soft delete — lượt làm và điểm của học sinh vẫn còn để tra cứu.
        $exam->delete();

        return redirect()->route('teacher.exams.index')->with('status', 'Đã xoá đề.');
    }

    // --- Câu hỏi trong đề -------------------------------------------------------

    public function addQuestions(AddExamQuestionsRequest $request, Exam $exam): RedirectResponse
    {
        $data = $request->validated();

        try {
            $added = $this->builder->addQuestions($exam, $data['question_ids'], $request->user());
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', "Đã thêm {$added} câu hỏi vào đề.");
    }

    public function addRandom(AddRandomExamQuestionsRequest $request, Exam $exam): RedirectResponse
    {
        $data = $request->validated();

        if ($data['easy'] + $data['medium'] + $data['hard'] !== 100) {
            return back()->withInput()->with('error', 'Tổng tỉ lệ Dễ + Trung bình + Khó phải bằng 100%.');
        }

        try {
            $added = $this->builder->addRandomQuestions(
                $exam,
                $data['topic_ids'] ?? [],
                $data['count'],
                ['easy' => $data['easy'], 'medium' => $data['medium'], 'hard' => $data['hard']],
                $request->user(),
            );
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = $added < $data['count']
            ? "Ngân hàng chỉ đủ {$added}/{$data['count']} câu phù hợp — đã thêm {$added} câu."
            : "Đã bốc ngẫu nhiên {$added} câu hỏi.";

        return back()->with('status', $message);
    }

    public function updateQuestion(UpdateExamQuestionRequest $request, Exam $exam, Question $question): RedirectResponse
    {
        $data = $request->validated();

        try {
            $this->builder->updateQuestion($exam, $question, (float) $data['points'], (int) $data['sort_order']);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Đã cập nhật câu hỏi trong đề.');
    }

    public function removeQuestion(Exam $exam, Question $question): RedirectResponse
    {
        $this->authorize('update', $exam);

        try {
            $this->builder->removeQuestion($exam, $question);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('status', 'Đã gỡ câu hỏi khỏi đề.');
    }
}
