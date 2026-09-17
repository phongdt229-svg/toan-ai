<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * MoMo gọi server-to-server (§8): không CSRF, không auth — căn cứ duy nhất là chữ ký.
 * Luôn trả 204 như MoMo yêu cầu, kể cả khi từ chối: không cho bên gọi dò lý do.
 */
class MomoIpnController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): Response
    {
        $payments->handleNotification(
            $request->json()->all() ?: $request->all(),
            $request->ip(),
            ['user-agent' => $request->userAgent(), 'content-type' => $request->header('content-type')],
        );

        return response()->noContent();
    }
}
