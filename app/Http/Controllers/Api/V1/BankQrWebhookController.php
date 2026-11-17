<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook biến động số dư (định dạng SePay) cho chuyển khoản QR — server-to-server, căn cứ duy nhất là
 * header "Authorization: Apikey <BANK_QR_WEBHOOK_SECRET>".
 *
 * Header Authorization cố ý KHÔNG ghi vào log webhook: log là nơi quản trị xem được, lộ khoá là ai cũng
 * giả được "đã nhận tiền". SePay gửi lại khi nhận mã khác 2xx, nên chỉ trả 500 khi lỗi phía mình.
 */
class BankQrWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $result = $payments->handleNotification(
            $request->json()->all() ?: $request->all(),
            $request->ip(),
            ['user-agent' => $request->userAgent()],
            Payment::METHOD_BANK_QR,
        );

        $status = match ($result) {
            PaymentWebhookLog::RESULT_INVALID_SIGNATURE => 401,
            PaymentWebhookLog::RESULT_ERROR => 500,
            default => 200,
        };

        return response()->json(['success' => $status === 200], $status);
    }
}
