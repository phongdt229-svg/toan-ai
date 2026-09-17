<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\SchoolClass;
use App\Services\Teaching\ClassReportService;
use App\Services\Teaching\StudentInsightService;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private readonly ClassReportService $reports) {}

    public function index(Request $request): View
    {
        $teacher = $request->user();
        $classes = SchoolClass::query()->taughtBy($teacher)->orderByRaw("status = 'archived'")->orderBy('name')->get();

        $class = $request->filled('lop')
            ? $classes->firstWhere('id', $request->integer('lop'))
            : $classes->first();

        abort_if($request->filled('lop') && ! $class, 404);

        return view('teacher.reports.index', [
            'classes' => $classes,
            'class' => $class,
            'report' => $class ? $this->reports->forClass($teacher, $class) : null,
            'filterLabels' => StudentInsightService::FILTERS,
        ]);
    }

    /** Bảng điểm học sinh của lớp dạng CSV (mở bằng Excel). */
    public function export(Request $request, SchoolClass $class): StreamedResponse
    {
        abort_unless(SchoolClass::query()->taughtBy($request->user())->whereKey($class->id)->exists(), 404);

        $report = $this->reports->forClass($request->user(), $class);

        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Học sinh', 'Email', 'Bài được giao', 'Đã làm', 'Quá hạn', 'Điểm TB (%)', 'Chủ đề yếu', 'Ghi chú']);

            foreach ($report['students'] as $row) {
                fputcsv($out, [
                    $row['student']->name,
                    $row['student']->email,
                    $row['assigned'],
                    $row['done'],
                    $row['overdue'],
                    $row['avg_percent'] ?? '',
                    $row['weak_topics'],
                    collect($row['flags'])->map(fn ($f) => StudentInsightService::FILTERS[$f] ?? $f)->implode(', '),
                ]);
            }

            fclose($out);
        }, 'bao-cao-lop-'.str($class->name)->slug().'-'.now()->format('Ymd').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
