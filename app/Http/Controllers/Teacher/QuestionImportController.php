<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ImportQuestionsRequest;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Topic;
use App\Services\AuditLogger;
use App\Services\Teaching\QuestionImporter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\View\View;

class QuestionImportController extends Controller
{
    public function __construct(
        private readonly QuestionImporter $importer,
        private readonly AuditLogger $audit,
    ) {}

    public function create(Request $request): View
    {
        $this->authorize('create', Question::class);

        $grade = $request->filled('grade')
            ? Grade::where('slug', $request->string('grade'))->firstOrFail()
            : Grade::active()->ordered()->firstOrFail();

        return view('teacher.questions.import', [
            'grade' => $grade,
            'grades' => Grade::active()->ordered()->get(),
            // Giáo viên cần biết id chủ đề để điền vào cột topic_id.
            'topics' => Topic::query()
                ->whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $grade->id))
                ->with('chapter')
                ->ordered()
                ->get(),
        ]);
    }

    public function store(ImportQuestionsRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $grade = Grade::findOrFail($validated['grade_id']);

        $result = $this->importer->import(
            $request->file('file'),
            $grade,
            $request->user(),
            $validated['status'],
        );

        $this->audit->log('question.imported', null, null, [
            'grade' => $grade->name,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);

        return back()
            ->with('status', "Đã nhập {$result['imported']} câu hỏi, bỏ qua {$result['skipped']} dòng.")
            ->with('import_errors', $result['errors']);
    }

    /** Tệp mẫu để giáo viên không phải đoán tên cột. */
    public function template(): Response
    {
        $rows = [
            ['type', 'difficulty', 'points', 'content', 'explanation', 'topic_id',
                'option_1', 'option_2', 'option_3', 'option_4', 'correct', 'accepted'],
            ['single_choice', 'easy', '1', 'Kết quả của $\frac{1}{2}+\frac{1}{2}$ là?',
                'Cùng mẫu số nên cộng tử.', '1', '1', '2', '1/4', '3/4', '1', ''],
            ['multiple_choice', 'medium', '2', 'Phân số nào bằng $\frac{1}{2}$?',
                'Rút gọn về 1/2.', '1', '2/4', '3/6', '2/5', '4/9', '1,2', ''],
            ['true_false', 'easy', '1', '$\frac{1}{2}+\frac{1}{3}=\frac{2}{5}$',
                'Phải quy đồng mẫu số trước.', '1', '', '', '', '', 'false', ''],
            ['fill_blank', 'medium', '2', 'Quy đồng: $\frac{1}{2}=\frac{?}{6}$ và $\frac{1}{3}=\frac{?}{6}$',
                'Nhân cả tử và mẫu.', '1', '', '', '', '', '3 ; 2', ''],
            ['short_answer', 'medium', '1', 'Tính $\frac{1}{2}+\frac{1}{3}$',
                'Quy đồng rồi cộng tử.', '1', '', '', '', '', '', '5/6'],
        ];

        $csv = "\xEF\xBB\xBF";
        foreach ($rows as $row) {
            $csv .= implode(',', array_map(
                fn ($v) => '"'.str_replace('"', '""', (string) $v).'"',
                $row,
            ))."\r\n";
        }

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="mau-cau-hoi.csv"',
        ]);
    }
}
