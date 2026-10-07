<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Services\Payment\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * IPN VNPAY: GET server-to-server, không auth — căn cứ duy nhất là chữ ký.
 *
 * Khác MoMo, VNPAY đọc `RspCode` để quyết định có gửi lại hay không: 00/02 là xong, mã khác VNPAY sẽ gửi lại.
 * Nên lỗi phía mình (99) phải trả mã khác 00 để được gửi lại, còn "đã ghi nhận thất bại" vẫn là 00.
 */
class VnpayIpnController extends Controller
{
    private const RESPONSES = [
        PaymentWebhookLog::RESULT_PROCESSED => ['00', 'Confirm Success'],
        PaymentWebhookLog::RESULT_PAYMENT_FAILED => ['00', 'Confirm Success'],
        PaymentWebhookLog::RESULT_PENDING => ['00', 'Confirm Success'],
        PaymentWebhookLog::RESULT_DUPLICATE => ['02', 'Order already confirmed'],
        PaymentWebhookLog::RESULT_NOT_FOUND => ['01', 'Order not found'],
        PaymentWebhookLog::RESULT_AMOUNT_MISMATCH => ['04', 'Invalid amount'],
        PaymentWebhookLog::RESULT_INVALID_SIGNATURE => ['97', 'Invalid signature'],
    ];

    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $result = $payments->handleNotification(
            $request->query(),
            $request->ip(),
            ['user-agent' => $request->userAgent()],
            Payment::METHOD_VNPAY,
        );

        [$code, $message] = self::RESPONSES[$result] ?? ['99', 'Unknown error'];

        return response()->json(['RspCode' => $code, 'Message' => $message]);
    }
}
