<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SupportTicketController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function index(Request $request): View
    {
        $type = $request->string('type')->toString();
        $status = $request->string('status')->toString();
        $search = trim($request->string('q')->toString());

        return view('admin.support.index', [
            'tickets' => SupportTicket::query()
                ->with('user:id,name', 'handler:id,name')
                ->when(array_key_exists($type, SupportTicket::TYPE_LABELS), fn ($q) => $q->where('type', $type))
                ->when($status === 'open', fn ($q) => $q->open())
                ->when(array_key_exists($status, SupportTicket::STATUS_LABELS), fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w
                    ->where('code', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('subject', 'like', "%{$search}%")))
                ->latest('id')
                ->paginate(config('site.per_page'))
                ->withQueryString(),
            'type' => $type,
            'status' => $status,
            'search' => $search,
            'stats' => [
                'total' => SupportTicket::count(),
                'new' => SupportTicket::where('status', SupportTicket::STATUS_NEW)->count(),
                'in_progress' => SupportTicket::where('status', SupportTicket::STATUS_IN_PROGRESS)->count(),
                'handled' => SupportTicket::whereIn('status', [SupportTicket::STATUS_RESOLVED, SupportTicket::STATUS_CLOSED])->count(),
                'content_error' => SupportTicket::where('type', SupportTicket::TYPE_CONTENT_ERROR)->open()->count(),
                // Giờ trung bình từ lúc gửi tới lúc admin đổi trạng thái xong — null nếu chưa xử lý ai.
                'avg_resolution_hours' => SupportTicket::whereNotNull('handled_at')
                    ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, created_at, handled_at)) h')
                    ->value('h'),
            ],
            'byType' => SupportTicket::query()
                ->groupBy('type')
                ->selectRaw('type, COUNT(*) c')
                ->pluck('c', 'type'),
            'daily' => $this->dailyForChart(),
        ]);
    }

    /**
     * Chuỗi 14 ngày cho biểu đồ. Ngày không có yêu cầu vẫn có mặt (giá trị 0) để trục thời gian liền mạch.
     *
     * @return list<array{label: string, count: int}>
     */
    private function dailyForChart(): array
    {
        $since = today()->subDays(13);

        $rows = SupportTicket::query()
            ->whereDate('created_at', '>=', $since)
            ->groupByRaw('DATE(created_at)')
            ->selectRaw('DATE(created_at) d, COUNT(*) c')
            ->pluck('c', 'd');

        return collect(range(0, 13))->map(function (int $i) use ($since, $rows) {
            $day = $since->copy()->addDays($i)->toDateString();

            return ['label' => Carbon::parse($day)->format('d/m'), 'count' => (int) ($rows[$day] ?? 0)];
        })->all();
    }

    public function show(SupportTicket $ticket): View
    {
        return view('admin.support.show', ['ticket' => $ticket->load('user', 'handler')]);
    }

    public function update(Request $request, SupportTicket $ticket): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::in(array_keys(SupportTicket::STATUS_LABELS))],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ], [], ['status' => 'trạng thái', 'admin_note' => 'ghi chú']);

        $this->tickets->update($ticket, $request->user(), $data['status'], $data['admin_note'] ?? null);

        return back()->with('status', 'Đã cập nhật yêu cầu '.$ticket->code.'.');
    }
}
