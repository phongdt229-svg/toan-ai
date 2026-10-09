<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use App\Services\Payment\GatewayRefund;
use App\Services\Payment\PaymentException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Stripe Checkout (ST-01 → ST-03): người dùng nhập thẻ trên trang do Stripe host — hệ thống không bao giờ thấy số thẻ.
 *
 * Gọi REST API trực tiếp (form-encoded) như MoMo/VNPAY, không thêm SDK.
 * Webhook: payload truyền vào verify/parse là ['raw' => thân request NGUYÊN VĂN, 'signature' => header Stripe-Signature] —
 * chữ ký tính trên chuỗi thô, decode JSON trước rồi encode lại là sai chữ ký.
 *
 * Thu VND: đơn vị không thập phân, 699.000₫ gửi `unit_amount=699000` (không nhân 100 như VNPAY).
 */
class StripeGateway implements PaymentGatewayInterface
{
    /** Stripe bắt Checkout Session sống ít nhất 30 phút. */
    private const MIN_SESSION_MINUTES = 31;

    public function name(): string
    {
        return Payment::METHOD_STRIPE;
    }

    public function createPayment(Payment $payment, string $orderInfo): GatewayCheckout
    {
        if (blank($this->credentials()['secret_key'])) {
            Log::error('Stripe chưa cấu hình STRIPE_SECRET_KEY.');
            throw new PaymentException('Cổng thanh toán đang bảo trì, vui lòng thử lại sau.');
        }

        $expiresAt = max(
            $payment->expires_at?->timestamp ?? 0,
            now()->addMinutes(self::MIN_SESSION_MINUTES)->timestamp,
        );

        $body = [
            'mode' => 'payment',
            'client_reference_id' => $payment->order_code,
            'metadata[order_code]' => $payment->order_code,
            'payment_intent_data[metadata][order_code]' => $payment->order_code,
            'line_items[0][quantity]' => 1,
            'line_items[0][price_data][currency]' => $this->credentials()['currency'],
            'line_items[0][price_data][unit_amount]' => $payment->amountInt(), // ← giá DB, VND không nhân 100
            'line_items[0][price_data][product_data][name]' => $orderInfo,
            'success_url' => route('payment.return.stripe', ['orderId' => $payment->order_code]),
            'cancel_url' => route('payment.return.stripe', ['orderId' => $payment->order_code]),
            'expires_at' => $expiresAt,
            'locale' => 'vi',
        ];

        try {
            // Idempotency-Key = mã đơn: bấm "Thanh toán" hai lần (hoặc request lặp do mạng) không đẻ hai phiên.
            $response = $this->http()->withHeaders(['Idempotency-Key' => 'checkout-'.$payment->order_code])
                ->post('/checkout/sessions', $body);
        } catch (ConnectionException $e) {
            Log::warning('Stripe create: không kết nối được', ['order' => $payment->order_code, 'error' => $e->getMessage()]);
            throw new PaymentException('Không kết nối được cổng thanh toán, vui lòng thử lại.');
        }

        $json = (array) $response->json();

        if (! $response->successful() || blank($json['url'] ?? null)) {
            Log::warning('Stripe create thất bại', ['order' => $payment->order_code, 'status' => $response->status(), 'error' => $json['error']['message'] ?? null]);
            throw new PaymentException('Cổng thanh toán từ chối tạo giao dịch: '.($json['error']['message'] ?? 'lỗi không xác định').'.');
        }

        return new GatewayCheckout($json['url'], (string) $json['id'], ['session_id' => $json['id']]);
    }

    public function verifyNotification(array $payload): bool
    {
        $raw = $payload['raw'] ?? null;
        $header = $payload['signature'] ?? null;
        $secret = (string) $this->credentials()['webhook_secret'];

        if (! is_string($raw) || ! is_string($header) || $secret === '') {
            return false;
        }

        // Header dạng "t=1700000000,v1=abc...,v1=def..." — có thể nhiều v1 khi Stripe đang xoay secret.
        $parts = collect(explode(',', $header))
            ->map(fn ($p) => explode('=', trim($p), 2))
            ->filter(fn ($kv) => count($kv) === 2);
        $timestamp = (int) ($parts->first(fn ($kv) => $kv[0] === 't')[1] ?? 0);
        $signatures = $parts->filter(fn ($kv) => $kv[0] === 'v1')->map(fn ($kv) => $kv[1]);

        if ($timestamp === 0 || abs(time() - $timestamp) > (int) $this->credentials()['tolerance']) {
            return false;
        }

        $expected = $this->signWebhook($raw, $timestamp);

        return $signatures->contains(fn ($s) => hash_equals($expected, $s));
    }

    public function parseNotification(array $payload): GatewayNotification
    {
        $event = (array) json_decode((string) $payload['raw'], true);

        return $this->toNotification((array) ($event['data']['object'] ?? []), (string) ($event['type'] ?? ''), $event);
    }

    public function notificationOrderCode(array $payload): ?string
    {
        $event = json_decode((string) ($payload['raw'] ?? ''), true);
        $object = $event['data']['object'] ?? [];

        return is_string($object['client_reference_id'] ?? null) ? $object['client_reference_id']
            : (is_string($object['metadata']['order_code'] ?? null) ? $object['metadata']['order_code'] : null);
    }

    public function queryStatus(Payment $payment): ?GatewayNotification
    {
        if (blank($this->credentials()['secret_key']) || blank($payment->gateway_request_id)) {
            return null;
        }

        try {
            $response = $this->http()->get('/checkout/sessions/'.urlencode((string) $payment->gateway_request_id));
        } catch (ConnectionException) {
            return null;
        }

        $session = (array) $response->json();

        if (! $response->successful() || ($session['client_reference_id'] ?? null) !== $payment->order_code) {
            return null;
        }

        return $this->toNotification($session, 'query', $session);
    }

    public function refund(Payment $payment, string $refundCode, int $amount, string $reason): GatewayRefund
    {
        if (blank($this->credentials()['secret_key'])) {
            throw new PaymentException('Cổng thanh toán chưa được cấu hình, không thể hoàn tiền.');
        }

        if (blank($payment->gateway_transaction_id)) {
            throw new PaymentException('Đơn này chưa có mã giao dịch Stripe (PaymentIntent) nên không hoàn được.');
        }

        try {
            $response = $this->http()->withHeaders(['Idempotency-Key' => 'refund-'.$refundCode])->post('/refunds', [
                'payment_intent' => $payment->gateway_transaction_id,
                'amount' => $amount,
                'reason' => 'requested_by_customer',
                'metadata[refund_code]' => $refundCode,
                'metadata[note]' => Str::limit($reason, 450, ''),
            ]);
        } catch (ConnectionException $e) {
            Log::warning('Stripe refund: không nhận được phản hồi', ['order' => $payment->order_code, 'refund' => $refundCode, 'error' => $e->getMessage()]);
            throw new PaymentException('Không nhận được phản hồi từ Stripe — chưa rõ đã hoàn hay chưa. Kiểm tra trên Stripe Dashboard trước khi làm lại.');
        }

        $json = (array) $response->json();

        if (! $response->successful()) {
            return new GatewayRefund(false, null, $response->status(), (string) ($json['error']['message'] ?? 'Stripe từ chối hoàn tiền'), $json);
        }

        // "pending" = Stripe đã nhận lệnh, tiền đang về thẻ (vài ngày) — với sổ sách của mình là đã hoàn.
        return new GatewayRefund(
            succeeded: in_array($json['status'] ?? null, ['succeeded', 'pending'], true),
            transactionId: $json['id'] ?? null,
            resultCode: in_array($json['status'] ?? null, ['succeeded', 'pending'], true) ? 0 : 1,
            message: (string) ($json['status'] ?? ''),
            raw: $json,
        );
    }

    /**
     * Gọi thử API đọc tài khoản (lệnh `payments:check-stripe`). Không tạo gì, không trừ tiền.
     *
     * @return array{ok: bool, mode: ?string, message: string, account: ?string}
     *
     * @throws ConnectionException
     */
    public function probe(): array
    {
        $key = (string) $this->credentials()['secret_key'];
        $mode = str_starts_with($key, 'sk_live_') || str_starts_with($key, 'rk_live_') ? 'live'
            : (str_starts_with($key, 'sk_test_') || str_starts_with($key, 'rk_test_') ? 'test' : null);

        $response = $this->http()->get('/balance');
        $json = (array) $response->json();

        return [
            'ok' => $response->successful(),
            'mode' => $mode,
            'message' => $response->successful()
                ? 'Kết nối được Stripe, secret key hợp lệ ('.($mode ?? 'không rõ chế độ').' mode).'
                : 'Stripe từ chối key: '.($json['error']['message'] ?? 'HTTP '.$response->status()),
            'account' => $response->header('Stripe-Account') ?: null,
        ];
    }

    /** Chữ ký webhook theo chuẩn Stripe v1 — test và trang giả lập dùng để tạo webhook hợp lệ. */
    public function signWebhook(string $raw, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp.'.'.$raw, (string) $this->credentials()['webhook_secret']);
    }

    // --------------------------------------------------------------------------------------

    /**
     * Quy Checkout Session về chuẩn chung (0 = đã trả). Sự kiện không liên quan → mã đơn rỗng → "không tìm thấy", bỏ qua.
     *
     * @param  array<string, mixed>  $session
     * @param  array<string, mixed>  $raw
     */
    private function toNotification(array $session, string $type, array $raw): GatewayNotification
    {
        $orderCode = (string) ($session['client_reference_id'] ?? $session['metadata']['order_code'] ?? '');
        $paid = ($session['payment_status'] ?? null) === 'paid';
        $currencyOk = strtolower((string) ($session['currency'] ?? '')) === $this->credentials()['currency'];

        [$code, $pending, $message] = match (true) {
            $type === 'checkout.session.async_payment_failed' => [1, false, 'Thanh toán không thành công.'],
            $type === 'checkout.session.expired', ($session['status'] ?? null) === 'expired' => [2, false, 'Phiên thanh toán đã hết hạn.'],
            $paid => [0, false, 'Thanh toán thành công.'],
            default => [3, true, 'Đang chờ thanh toán.'], // open / complete-unpaid (chuyển khoản chậm…)
        };

        return new GatewayNotification(
            orderCode: $orderCode,
            // Sai loại tiền thì cố ý cho lệch số tiền → PaymentService đánh dấu, không cấp gói.
            amount: $currencyOk ? (int) ($session['amount_total'] ?? 0) : -1,
            transactionId: is_string($session['payment_intent'] ?? null) ? $session['payment_intent'] : null,
            resultCode: $code,
            message: $message,
            raw: $raw,
            pending: $pending,
        );
    }

    private function http(): PendingRequest
    {
        return Http::baseUrl($this->credentials()['api_base'])
            ->withToken((string) $this->credentials()['secret_key'])
            ->asForm()
            ->acceptJson()
            ->timeout((int) $this->credentials()['timeout']);
    }

    /** @return array<string, mixed> */
    protected function credentials(): array
    {
        return config('payment.stripe');
    }
}
