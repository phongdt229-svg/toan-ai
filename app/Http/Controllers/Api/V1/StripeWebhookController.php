<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Webhook Stripe (ST-02): server-to-server, không auth — căn cứ duy nhất là header Stripe-Signature tính trên thân request THÔ.
 *
 * Stripe gửi lại khi nhận mã khác 2xx: chỉ trả 400 cho chữ ký sai (không bao giờ hợp lệ, gửi lại vô ích)
 * và 500 khi lỗi phía mình (để được gửi lại); còn lại 200, kể cả sự kiện không liên quan.
 */
class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $result = $payments->handleNotification(
            ['raw' => $request->getContent(), 'signature' => $request->header('Stripe-Signature')],
            $request->ip(),
            ['user-agent' => $request->userAgent()],
            Payment::METHOD_STRIPE,
        );

        $status = match ($result) {
            PaymentWebhookLog::RESULT_INVALID_SIGNATURE => 400,
            PaymentWebhookLog::RESULT_ERROR => 500,
            default => 200,
        };

        return response()->json(['received' => $status === 200], $status);
    }
}
