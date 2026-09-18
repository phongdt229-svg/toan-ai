<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Http\Requests\SupportTicketRequest;
use App\Models\SupportTicket;
use App\Services\SupportTicketService;
use App\Support\MathCaptcha;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupportController extends Controller
{
    public function __construct(private readonly SupportTicketService $tickets) {}

    public function create(Request $request): View
    {
        $type = $request->string('loai')->toString();

        return view('public.support.create', [
            'type' => array_key_exists($type, SupportTicket::TYPE_LABELS) ? $type : SupportTicket::TYPE_SUPPORT,
            // Trang người dùng vừa xem (từ link "Báo lỗi nội dung" trong bài học).
            'contextUrl' => $request->string('tu')->toString() ?: url()->previous(),
            'captcha' => MathCaptcha::question(),
        ]);
    }

    public function store(SupportTicketRequest $request): RedirectResponse
    {
        $request->ensureIsNotRateLimited();

        $ticket = $this->tickets->create($request->validated(), $request);

        return redirect()->route('support.create')
            ->with('status', "Đã nhận yêu cầu của bạn — mã {$ticket->code}. "
                .'Chúng tôi phản hồi qua email trong vòng 7 ngày làm việc.');
    }
}
