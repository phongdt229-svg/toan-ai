<?php

namespace App\Services;

use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketReceived;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;

/** Nhận yêu cầu hỗ trợ / báo lỗi nội dung và chuyển trạng thái xử lý (§25). */
class SupportTicketService
{
    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array<string, mixed>  $data  đã qua SupportTicketRequest
     */
    public function create(array $data, Request $request): SupportTicket
    {
        $ticket = SupportTicket::create([
            'code' => SupportTicket::generateCode(),
            'type' => $data['type'],
            'user_id' => $request->user()?->id,
            'name' => $data['name'],
            'email' => $data['email'],
            'subject' => $data['subject'],
            'message' => $data['message'],
            'context_url' => $data['context_url'] ?? null,
            'related_type' => $data['related_type'] ?? null,
            'related_id' => $data['related_id'] ?? null,
            'status' => SupportTicket::STATUS_NEW,
            'ip_address' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 191),
        ]);

        $this->notifyStaff($ticket);

        return $ticket;
    }

    /** Đổi trạng thái / ghi chú xử lý. */
    public function update(SupportTicket $ticket, User $admin, string $status, ?string $note): SupportTicket
    {
        $old = $ticket->only(['status', 'admin_note']);

        $ticket->update([
            'status' => $status,
            'admin_note' => $note,
            'handled_by' => $admin->id,
            'handled_at' => now(),
        ]);

        $this->audit->log('support.updated', $ticket, $old, $ticket->only(['status', 'admin_note']));

        return $ticket;
    }

    /**
     * Báo cho bộ phận hỗ trợ. Không có email cấu hình thì bỏ qua — yêu cầu vẫn nằm trong
     * trang Quản trị → Hỗ trợ, không mất.
     */
    private function notifyStaff(SupportTicket $ticket): void
    {
        $inbox = config('site.email');

        if (blank($inbox)) {
            return;
        }

        Notification::route('mail', $inbox)->notify(new SupportTicketReceived($ticket));
    }
}
