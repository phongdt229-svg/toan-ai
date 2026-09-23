<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use App\Services\Payment\GatewayRefund;
use App\Services\Payment\PaymentException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * MoMo API v2 (captureWallet). Chữ ký HMAC-SHA256 trên chuỗi key=value xếp theo alphabet,
 * đúng thứ tự trong tài liệu MoMo — sai thứ tự là sai chữ ký.
 */
class MomoGateway implements PaymentGatewayInterface
{
    private const CREATE_FIELDS = ['accessKey', 'amount', 'extraData', 'ipnUrl', 'orderId', 'orderInfo', 'partnerCode', 'redirectUrl', 'requestId', 'requestType'];

    private const IPN_FIELDS = ['accessKey', 'amount', 'extraData', 'message', 'orderId', 'orderInfo', 'orderType', 'partnerCode', 'payType', 'requestId', 'responseTime', 'resultCode', 'transId'];

    private const REFUND_FIELDS = ['accessKey', 'amount', 'description', 'orderId', 'partnerCode', 'requestId', 'transId'];

    private const QUERY_FIELDS = ['accessKey', 'orderId', 'partnerCode', 'requestId'];

    public function name(): string
    {
        return 'momo';
    }

    public function createPayment(Payment $payment, string $orderInfo): GatewayCheckout
    {
        $config = $this->credentials();

        if (blank($config['partner_code']) || blank($config['access_key']) || blank($config['secret_key'])) {
            Log::error('MoMo chưa cấu hình MOMO_PARTNER_CODE / MOMO_ACCESS_KEY / MOMO_SECRET_KEY.');
            throw new PaymentException('Cổng thanh toán đang bảo trì, vui lòng thử lại sau.');
        }

        $body = [
            'partnerCode' => $config['partner_code'],
            'requestId' => (string) Str::uuid(),
            'amount' => (string) $payment->amountInt(),
            'orderId' => $payment->order_code,
            'orderInfo' => $orderInfo,
            'redirectUrl' => $config['return_url'] ?: route('payment.return'),
            'ipnUrl' => $config['ipn_url'] ?: route('api.payment.momo.ipn'),
            'extraData' => '',
            'requestType' => $config['request_type'],
        ];
        // accessKey chỉ nằm trong chuỗi ký, không gửi đi.
        $body['signature'] = $this->sign($body, self::CREATE_FIELDS);
        $body['lang'] = 'vi';

        try {
            $response = Http::timeout($config['timeout'])
                ->acceptJson()
                ->post($config['endpoint'].'/v2/gateway/api/create', $body);
        } catch (ConnectionException $e) {
            Log::warning('MoMo create: không kết nối được', ['order' => $payment->order_code, 'error' => $e->getMessage()]);
            throw new PaymentException('Không kết nối được MoMo, vui lòng thử lại.');
        }

        $json = (array) $response->json();

        if (! $response->successful() || (int) ($json['resultCode'] ?? -1) !== 0 || blank($json['payUrl'] ?? null)) {
            Log::warning('MoMo create thất bại', ['order' => $payment->order_code, 'status' => $response->status(), 'body' => $json]);
            throw new PaymentException('MoMo từ chối tạo giao dịch: '.($json['message'] ?? 'lỗi không xác định').'.');
        }

        return new GatewayCheckout($json['payUrl'], $body['requestId'], $json);
    }

    public function verifyNotification(array $payload): bool
    {
        $signature = $payload['signature'] ?? null;

        if (! is_string($signature) || ($payload['partnerCode'] ?? null) !== $this->credentials()['partner_code']) {
            return false;
        }

        foreach (self::IPN_FIELDS as $field) {
            if ($field !== 'accessKey' && ! array_key_exists($field, $payload)) {
                return false;
            }
        }

        return hash_equals($this->sign($payload, self::IPN_FIELDS), $signature);
    }

    public function parseNotification(array $payload): GatewayNotification
    {
        return new GatewayNotification(
            orderCode: (string) $payload['orderId'],
            amount: (int) $payload['amount'],
            transactionId: filled($payload['transId'] ?? null) ? (string) $payload['transId'] : null,
            resultCode: (int) $payload['resultCode'],
            message: (string) ($payload['message'] ?? ''),
            raw: $payload,
        );
    }

    public function queryStatus(Payment $payment): ?GatewayNotification
    {
        $config = $this->credentials();

        if (blank($config['partner_code']) || blank($config['secret_key'])) {
            return null;
        }

        $body = [
            'partnerCode' => $config['partner_code'],
            'requestId' => (string) Str::uuid(),
            'orderId' => $payment->order_code,
        ];
        $body['signature'] = $this->sign($body, self::QUERY_FIELDS);
        $body['lang'] = 'vi';

        try {
            $json = (array) Http::timeout($config['timeout'])->acceptJson()
                ->post($config['endpoint'].'/v2/gateway/api/query', $body)
                ->json();
        } catch (ConnectionException) {
            return null;
        }

        if (! isset($json['resultCode'], $json['orderId']) || $json['orderId'] !== $payment->order_code) {
            return null;
        }

        return new GatewayNotification(
            orderCode: (string) $json['orderId'],
            amount: (int) ($json['amount'] ?? 0),
            transactionId: filled($json['transId'] ?? null) ? (string) $json['transId'] : null,
            resultCode: (int) $json['resultCode'],
            message: (string) ($json['message'] ?? ''),
            raw: $json,
        );
    }

    public function refund(Payment $payment, string $refundCode, int $amount, string $reason): GatewayRefund
    {
        $config = $this->credentials();

        if (blank($config['partner_code']) || blank($config['access_key']) || blank($config['secret_key'])) {
            throw new PaymentException('Cổng thanh toán chưa được cấu hình, không thể hoàn tiền.');
        }

        if (blank($payment->gateway_transaction_id)) {
            throw new PaymentException('Đơn này chưa có mã giao dịch MoMo (transId) nên không hoàn được.');
        }

        // orderId ở đây là mã của LẦN HOÀN, không phải mã đơn gốc; transId trỏ tới giao dịch cần hoàn.
        $body = [
            'partnerCode' => $config['partner_code'],
            'orderId' => $refundCode,
            'requestId' => (string) Str::uuid(),
            'amount' => (string) $amount,
            'transId' => (string) $payment->gateway_transaction_id,
            'description' => Str::limit($reason, 100, ''),
        ];
        $body['signature'] = $this->sign($body, self::REFUND_FIELDS);
        $body['lang'] = 'vi';

        try {
            $response = Http::timeout($config['timeout'])->acceptJson()->post($config['endpoint'].'/v2/gateway/api/refund', $body);
        } catch (ConnectionException $e) {
            Log::warning('MoMo refund: không nhận được phản hồi', ['order' => $payment->order_code, 'refund' => $refundCode, 'error' => $e->getMessage()]);
            throw new PaymentException('Không nhận được phản hồi từ MoMo — chưa rõ đã hoàn hay chưa. Kiểm tra trên cổng MoMo trước khi làm lại.');
        }

        $json = (array) $response->json();

        if (! isset($json['resultCode'])) {
            Log::warning('MoMo refund: phản hồi không hợp lệ', ['refund' => $refundCode, 'status' => $response->status(), 'body' => $json]);
            throw new PaymentException('MoMo trả về phản hồi không hợp lệ — kiểm tra trên cổng MoMo trước khi làm lại.');
        }

        return new GatewayRefund(
            succeeded: (int) $json['resultCode'] === 0,
            transactionId: filled($json['transId'] ?? null) ? (string) $json['transId'] : null,
            resultCode: (int) $json['resultCode'],
            message: (string) ($json['message'] ?? ''),
            raw: $json,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<string>  $fields
     */
    public function sign(array $data, array $fields): string
    {
        $data['accessKey'] = $this->credentials()['access_key'];

        $raw = collect($fields)
            ->map(fn (string $field) => $field.'='.($data[$field] ?? ''))
            ->implode('&');

        return hash_hmac('sha256', $raw, (string) $this->credentials()['secret_key']);
    }

    /** Ký payload IPN — test và trang giả lập dùng để tạo IPN hợp lệ. */
    public function signNotification(array $payload): string
    {
        return $this->sign($payload, self::IPN_FIELDS);
    }

    public function partnerCode(): ?string
    {
        return $this->credentials()['partner_code'];
    }

    /** @return array<string, mixed> */
    protected function credentials(): array
    {
        return config('payment.momo');
    }
}
