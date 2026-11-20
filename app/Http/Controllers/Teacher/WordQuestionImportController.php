<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teacher\ConfirmWordImportRequest;
use App\Http\Requests\Teacher\ImportWordQuestionsRequest;
use App\Models\Grade;
use App\Models\Question;
use App\Models\Topic;
use App\Services\AuditLogger;
use App\Services\Teaching\DocxQuestionParser;
use App\Services\Teaching\QuestionImporter;
use App\Support\Docx\SimpleDocx;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Illuminate\View\View;

/**
 * Nhập đề từ Word: tải lên → xem trước từng câu (đáp án, lỗi, cảnh báo) → bấm xác nhận mới lưu.
 *
 * Kết quả tách giữ ở cache phía server theo (giáo viên, mã ngẫu nhiên), form xác nhận chỉ gửi mã —
 * không để trình duyệt gửi lại nội dung câu hỏi (sẽ sửa được đáp án đúng bằng DevTools).
 */
class WordQuestionImportController extends Controller
{
    private const TTL_MINUTES = 60;

    public function __construct(
        private readonly DocxQuestionParser $parser,
        private readonly QuestionImporter $importer,
        private readonly AuditLogger $audit,
    ) {}

    public function upload(ImportWordQuestionsRequest $request): RedirectResponse
    {
        $grade = Grade::findOrFail($request->integer('grade_id'));
        $topicId = $request->filled('topic_id') ? $request->integer('topic_id') : null;

        if ($topicId !== null && ! $this->topicBelongsTo($topicId, $grade)) {
            return back()->withInput()->withErrors(['topic_id' => 'Chủ đề không thuộc '.$grade->name.'.']);
        }

        try {
            $parsed = $this->parser->parse($request->file('file')->getRealPath());
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->withErrors(['file' => $e->getMessage()]);
        }

        $token = Str::random(32);
        Cache::put($this->key($request, $token), [
            'grade_id' => $grade->id,
            'topic_id' => $topicId,
            'file_name' => Str::limit($request->file('file')->getClientOriginalName(), 120),
            'rows' => $parsed['rows'],
            'warnings' => $parsed['warnings'],
        ], now()->addMinutes(self::TTL_MINUTES));

        return redirect()->route('teacher.questions.import.word.preview', $token);
    }

    public function preview(Request $request, string $token): View|RedirectResponse
    {
        $this->authorize('create', Question::class);

        $data = Cache::get($this->key($request, $token));
        if (! $data) {
            return redirect()->route('teacher.questions.import')
                ->with('error', 'Bản xem trước đã hết hạn — hãy tải tệp Word lên lại.');
        }

        $rows = collect($data['rows']);

        return view('teacher.questions.import-word-preview', [
            'token' => $token,
            'data' => $data,
            'rows' => $rows,
            'valid' => $rows->filter(fn ($r) => empty($r['errors']))->count(),
            'grade' => Grade::findOrFail($data['grade_id']),
            'topic' => $data['topic_id'] ? Topic::with('chapter')->find($data['topic_id']) : null,
        ]);
    }

    public function confirm(ConfirmWordImportRequest $request, string $token): RedirectResponse
    {
        $key = $this->key($request, $token);
        // pull: bấm xác nhận hai lần không nhập trùng hai bộ câu hỏi.
        $data = Cache::pull($key);
        if (! $data) {
            return redirect()->route('teacher.questions.import')
                ->with('error', 'Bản xem trước đã hết hạn hoặc đã được nhập — hãy tải tệp Word lên lại.');
        }

        $grade = Grade::findOrFail($data['grade_id']);
        $result = $this->importer->importRows($data['rows'], $grade, $request->user(), $request->validated('status'), $data['topic_id']);

        $this->audit->log('question.imported', null, null, [
            'source' => 'docx',
            'grade' => $grade->name,
            'imported' => $result['imported'],
            'skipped' => $result['skipped'],
        ]);

        return redirect()->route('teacher.questions.index')
            ->with('status', "Đã nhập {$result['imported']} câu hỏi từ tệp Word, bỏ qua {$result['skipped']} câu.")
            ->with('import_errors', $result['errors']);
    }

    /** Đề mẫu đúng định dạng — giáo viên mở ra, gõ đè lên là được. */
    public function template(): Response
    {
        $frac = '<m:f><m:num><m:r><m:t>1</m:t></m:r></m:num><m:den><m:r><m:t>2</m:t></m:r></m:den></m:f>';
        $third = '<m:f><m:num><m:r><m:t>1</m:t></m:r></m:num><m:den><m:r><m:t>3</m:t></m:r></m:den></m:f>';
        $sqrt = '<m:rad><m:radPr><m:degHide m:val="1"/></m:radPr><m:deg/><m:e><m:r><m:t>16</m:t></m:r></m:e></m:rad>';

        $bytes = SimpleDocx::build([
            'ĐỀ MẪU — mỗi câu bắt đầu bằng "Câu N:". Công thức gõ bằng Insert → Equation (Alt + =).',
            'Đáp án đúng: ghi dòng "Đáp án: B", hoặc gạch chân / tô đỏ đáp án đúng. Dòng sau "Lời giải:" là lời giải.',
            ['Câu 1: Kết quả của ', ['math' => $frac], ' + ', ['math' => $third], ' là'],
            ['A. ', ['math' => '<m:f><m:num><m:r><m:t>2</m:t></m:r></m:num><m:den><m:r><m:t>5</m:t></m:r></m:den></m:f>'], ['tab' => true],
                'B. ', ['math' => '<m:f><m:num><m:r><m:t>5</m:t></m:r></m:num><m:den><m:r><m:t>6</m:t></m:r></m:den></m:f>'], ['tab' => true],
                'C. ', ['math' => '<m:f><m:num><m:r><m:t>1</m:t></m:r></m:num><m:den><m:r><m:t>6</m:t></m:r></m:den></m:f>'], ['tab' => true],
                'D. ', ['math' => '<m:f><m:num><m:r><m:t>2</m:t></m:r></m:num><m:den><m:r><m:t>6</m:t></m:r></m:den></m:f>']],
            'Đáp án: B',
            'Lời giải: Quy đồng mẫu số chung là 6 rồi cộng tử số.',
            'Mức độ: Dễ',
            ['Câu 2: ', ['math' => $sqrt], ' bằng'],
            ['A. 2', ['tab' => true], ['u' => 'B.'], ' 4', ['tab' => true], 'C. 8', ['tab' => true], 'D. 16'],
            'Câu 3 (0,5 điểm): Số 2 là số nguyên tố.',
            'Đáp án: Đúng',
            'Câu 4: Tính 12 : 4.',
            'Đáp án: 3',
            'Câu 5: Chứng minh rằng tổng ba góc của một tam giác bằng 180°.',
            'Lời giải: (câu không có "Đáp án" được nhập thành câu tự luận, giáo viên chấm tay)',
        ]);

        return response($bytes, 200, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'Content-Disposition' => 'attachment; filename="de-mau-math-ai.docx"',
        ]);
    }

    private function key(Request $request, string $token): string
    {
        return 'question-import:'.$request->user()->id.':'.$token;
    }

    private function topicBelongsTo(int $topicId, Grade $grade): bool
    {
        return Topic::whereKey($topicId)
            ->whereHas('chapter.subject', fn ($q) => $q->where('grade_id', $grade->id))
            ->exists();
    }
}
