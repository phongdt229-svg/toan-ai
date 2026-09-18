<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Nhật ký thao tác (§29) — chỉ đọc, không sửa/xoá được từ giao diện. */
class AuditLogController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.audit-logs.index', [
            'logs' => $this->filtered($request)->with('user:id,name,email')->latest('id')->paginate(config('site.per_page'))->withQueryString(),
            'actions' => AuditLog::query()->distinct()->orderBy('action')->pluck('action'),
            'filters' => $request->only(['action', 'actor', 'subject', 'from', 'to']),
        ]);
    }

    /** Xuất CSV theo bộ lọc hiện tại — đối chiếu khi có khiếu nại / kiểm toán. */
    public function export(Request $request): StreamedResponse
    {
        $query = $this->filtered($request)->with('user:id,name,email')->orderBy('id');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // BOM để Excel đọc đúng tiếng Việt
            fputcsv($out, ['ID', 'Thời gian', 'Người thực hiện', 'Email', 'Hành động', 'Đối tượng', 'Giá trị cũ', 'Giá trị mới', 'IP']);

            $query->chunk(500, function ($logs) use ($out) {
                foreach ($logs as $log) {
                    fputcsv($out, [
                        $log->id,
                        $log->created_at->format('Y-m-d H:i:s'),
                        $log->user?->name ?? 'Hệ thống',
                        $log->user?->email,
                        $log->action,
                        $log->subjectLabel(),
                        $log->old_values ? json_encode($log->old_values, JSON_UNESCAPED_UNICODE) : '',
                        $log->new_values ? json_encode($log->new_values, JSON_UNESCAPED_UNICODE) : '',
                        $log->ip_address,
                    ]);
                }
            });

            fclose($out);
        }, 'audit-log-'.now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /** @return Builder<AuditLog> */
    private function filtered(Request $request): Builder
    {
        $date = fn (string $key) => rescue(fn () => $request->filled($key) ? Carbon::parse($request->input($key)) : null, null, false);

        return AuditLog::query()
            ->when($request->filled('action'), fn ($q) => $q->where('action', $request->input('action')))
            ->when($request->filled('actor'), fn ($q) => $q->whereHas('user', fn ($u) => $u
                ->where('email', 'like', '%'.$request->input('actor').'%')))
            ->when($request->filled('subject'), fn ($q) => $q->where('auditable_type', 'like', '%'.$request->input('subject')))
            ->when($date('from'), fn ($q, $from) => $q->where('created_at', '>=', $from->startOfDay()))
            ->when($date('to'), fn ($q, $to) => $q->where('created_at', '<=', $to->endOfDay()));
    }
}
