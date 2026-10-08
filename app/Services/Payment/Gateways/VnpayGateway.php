<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use App\Services\Payment\GatewayRefund;
use App\Services\Payment\PaymentException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * VNPAY 2.1.0.
 *
 * - Thanh toán / IPN / return: HMAC-SHA512 trên query string các tham số `vnp_*` xếp theo tên, key và value đều urlencode.
 * - Truy vấn / hoàn tiền (merchant_webapi): HMAC-SHA512 trên chuỗi giá trị nối bằng `|` theo thứ tự cố định trong tài liệu.
 * - Số tiền gửi/nhận nhân 100 (không có phần lẻ). Mọi mốc giờ theo GMT+7.
 */
class VnpayGateway implements PaymentGatewayInterface
{
    protected const VERSION = '2.1.0';

    protected const TZ = 'Asia/Ho_Chi_Minh';

    /** vnp_TransactionStatus 01 = giao dịch chưa hoàn tất (người dùng chưa trả xong). */
    private const STATUS_INCOMPLETE = '01';

    private const MESSAGES = [
        '07' => 'Trừ tiền thành công nhưng giao dịch bị nghi ngờ.',
        '09' => 'Thẻ/tài khoản chưa đăng ký Internet Banking.',
        '10' => 'Xác thực thông tin thẻ/tài khoản sai quá 3 lần.',
        '11' => 'Hết hạn chờ thanh toán.',
        '12' => 'Thẻ/tài khoản bị khoá.',
        '13' => 'Nhập sai mật khẩu OTP.',
        '24' => 'Khách hàng huỷ giao dịch.',
        '51' => 'Tài khoản không đủ số dư.',
        '65' => 'Tài khoản vượt hạn mức giao dịch trong ngày.',
        '75' => 'Ngân hàng thanh toán đang bảo trì.',
        '79' => 'Nhập sai mật khẩu thanh toán quá số lần quy định.',
    ];

    public function name(): string
    {
        return Payment::METHOD_VNPAY;
    }

    public function createPayment(Payment $payment, string $orderInfo): GatewayCheckout
    {
        $config = $this->credentials();

        if (blank($config['tmn_code']) || blank($config['hash_secret'])) {
            Log::error('VNPAY chưa cấu hình VNPAY_TMN_CODE / VNPAY_HASH_SECRET.');
            throw new PaymentException('Cổng thanh toán đang bảo trì, vui lòng thử lại sau.');
        }

        $createDate = now(self::TZ)->format('YmdHis');

        $params = [
            'vnp_Version' => self::VERSION,
            'vnp_Command' => 'pay',
            'vnp_TmnCode' => $config['tmn_code'],
            'vnp_Amount' => $payment->amountInt() * 100,
            'vnp_CreateDate' => $createDate,
            'vnp_CurrCode' => 'VND',
            'vnp_IpAddr' => $payment->client_ip ?: '127.0.0.1',
            'vnp_Locale' => 'vn',
            'vnp_OrderInfo' => $orderInfo,
            'vnp_OrderType' => 'other',
            'vnp_ReturnUrl' => $config['return_url'] ?: route('payment.return.vnpay'),
            'vnp_TxnRef' => $payment->order_code,
        ];

        // Link hết hạn cùng lúc với đơn — trả sau giờ đó VNPAY tự từ chối, không có cảnh "trừ tiền mà đơn đã huỷ".
        if ($payment->expires_at) {
            $params['vnp_ExpireDate'] = $payment->expires_at->copy()->tz(self::TZ)->format('YmdHis');
        }

        $query = $this->buildQuery($params);
        $url = $config['pay_url'].'?'.$query.'&vnp_SecureHash='.$this->hmac($query);

        // vnp_CreateDate phải giữ lại: truy vấn và hoàn tiền đòi đúng mốc này (vnp_TransactionDate).
        return new GatewayCheckout($url, (string) Str::uuid(), ['vnp_CreateDate' => $createDate, 'vnp_TxnRef' => $payment->order_code]);
    }

    public function verifyNotification(array $payload): bool
    {
        $signature = $payload['vnp_SecureHash'] ?? null;

        if (! is_string($signature) || ($payload['vnp_TmnCode'] ?? null) !== $this->credentials()['tmn_code']) {
            return false;
        }

        foreach (['vnp_TxnRef', 'vnp_Amount', 'vnp_ResponseCode', 'vnp_TransactionStatus'] as $field) {
            if (! array_key_exists($field, $payload)) {
                return false;
            }
        }

        return hash_equals($this->signNotification($payload), strtolower($signature));
    }

    public function parseNotification(array $payload): GatewayNotification
    {
        return $this->toNotification($payload, $payload);
    }

    public function notificationOrderCode(array $payload): ?string
    {
        return is_scalar($payload['vnp_TxnRef'] ?? null) ? (string) $payload['vnp_TxnRef'] : null;
    }

    public function queryStatus(Payment $payment): ?GatewayNotification
    {
        $config = $this->credentials();

        if (blank($config['tmn_code']) || blank($config['hash_secret'])) {
            return null;
        }

        try {
            $json = $this->querydr($payment->order_code, $this->transactionDate($payment));
        } catch (ConnectionException) {
            return null;
        }

        // 00 = truy vấn được; 91 = không tìm thấy giao dịch (người dùng chưa vào trang VNPAY) — coi như chưa biết.
        if (($json['vnp_ResponseCode'] ?? null) !== '00' || ($json['vnp_TxnRef'] ?? null) !== $payment->order_code) {
            return null;
        }

        if (! $this->verifyApiResponse($json)) {
            Log::warning('VNPAY querydr: sai chữ ký phản hồi', ['order' => $payment->order_code]);

            return null;
        }

        // Phản hồi truy vấn: vnp_ResponseCode là kết quả CỦA LỆNH TRUY VẤN, trạng thái giao dịch nằm ở vnp_TransactionStatus.
        return $this->toNotification($json, ['vnp_ResponseCode' => $json['vnp_TransactionStatus'] ?? '99'] + $json);
    }

    /**
     * Kiểm tra cấu hình với VNPAY thật (lệnh `payments:check-vnpay`): truy vấn một mã đơn KHÔNG tồn tại.
     * VNPAY trả 91 (không tìm thấy giao dịch) nghĩa là mã website + chữ ký đúng; 97 = sai chữ ký (sai hash secret);
     * 02 = mã website (TMN code) không hợp lệ.
     *
     * @return array{ok: bool, code: ?string, message: string, response_signature_valid: ?bool, raw: array<string, mixed>}
     *
     * @throws ConnectionException
     */
    public function probe(): array
    {
        $json = $this->querydr('PROBE'.now()->format('ymdHis').Str::upper(Str::random(4)), now(self::TZ)->format('YmdHis'));
        $code = isset($json['vnp_ResponseCode']) ? (string) $json['vnp_ResponseCode'] : null;

        return [
            'ok' => $code === '91',
            'code' => $code,
            'message' => match ($code) {
                '91' => 'Kết nối được, mã website và hash secret đúng (VNPAY báo không tìm thấy đơn thử — đúng như mong đợi).',
                '97' => 'Sai chữ ký — kiểm tra VNPAY_HASH_SECRET (copy đủ, không thừa khoảng trắng).',
                '02' => 'Mã website không hợp lệ — kiểm tra VNPAY_TMN_CODE.',
                null => 'VNPAY không trả mã phản hồi — kiểm tra VNPAY_API_URL.',
                default => 'VNPAY trả mã '.$code.': '.($json['vnp_Message'] ?? ''),
            },
            // Phản hồi có chữ ký thì thử luôn thứ tự trường mình dùng để kiểm chữ ký phản hồi querydr.
            'response_signature_valid' => isset($json['vnp_SecureHash']) ? $this->verifyApiResponse($json) : null,
            'raw' => $json,
        ];
    }

    /** @return array<string, mixed> @throws ConnectionException */
    private function querydr(string $txnRef, string $transactionDate): array
    {
        $config = $this->credentials();

        $body = [
            'vnp_RequestId' => Str::limit(str_replace('-', '', (string) Str::uuid()), 32, ''),
            'vnp_Version' => self::VERSION,
            'vnp_Command' => 'querydr',
            'vnp_TmnCode' => $config['tmn_code'],
            'vnp_TxnRef' => $txnRef,
            'vnp_OrderInfo' => 'Truy van '.$txnRef,
            'vnp_TransactionDate' => $transactionDate,
            'vnp_CreateDate' => now(self::TZ)->format('YmdHis'),
            'vnp_IpAddr' => $this->serverIp(),
        ];
        $body['vnp_SecureHash'] = $this->hmac(implode('|', [
            $body['vnp_RequestId'], $body['vnp_Version'], $body['vnp_Command'], $body['vnp_TmnCode'], $body['vnp_TxnRef'],
            $body['vnp_TransactionDate'], $body['vnp_CreateDate'], $body['vnp_IpAddr'], $body['vnp_OrderInfo'],
        ]));

        return (array) Http::timeout($config['timeout'])->acceptJson()->post($config['api_url'], $body)->json();
    }

    public function refund(Payment $payment, string $refundCode, int $amount, string $reason): GatewayRefund
    {
        $config = $this->credentials();

        if (blank($config['tmn_code']) || blank($config['hash_secret'])) {
            throw new PaymentException('Cổng thanh toán chưa được cấu hình, không thể hoàn tiền.');
        }

        if (blank($payment->gateway_transaction_id)) {
            throw new PaymentException('Đơn này chưa có mã giao dịch VNPAY (vnp_TransactionNo) nên không hoàn được.');
        }

        $body = [
            'vnp_RequestId' => Str::limit($refundCode, 32, ''),
            'vnp_Version' => self::VERSION,
            'vnp_Command' => 'refund',
            'vnp_TmnCode' => $config['tmn_code'],
            // 02 hoàn toàn phần, 03 hoàn một phần.
            'vnp_TransactionType' => $amount >= $payment->amountInt() ? '02' : '03',
            'vnp_TxnRef' => $payment->order_code,
            'vnp_Amount' => $amount * 100,
            'vnp_OrderInfo' => Str::limit(Str::ascii($reason), 200, '') ?: 'Hoan tien',
            'vnp_TransactionNo' => (string) $payment->gateway_transaction_id,
            'vnp_TransactionDate' => $this->transactionDate($payment),
            'vnp_CreateBy' => 'toanai-admin',
            'vnp_CreateDate' => now(self::TZ)->format('YmdHis'),
            'vnp_IpAddr' => $this->serverIp(),
        ];
        $body['vnp_SecureHash'] = $this->hmac(implode('|', [
            $body['vnp_RequestId'], $body['vnp_Version'], $body['vnp_Command'], $body['vnp_TmnCode'], $body['vnp_TransactionType'],
            $body['vnp_TxnRef'], $body['vnp_Amount'], $body['vnp_TransactionNo'], $body['vnp_TransactionDate'],
            $body['vnp_CreateBy'], $body['vnp_CreateDate'], $body['vnp_IpAddr'], $body['vnp_OrderInfo'],
        ]));

        try {
            $response = Http::timeout($config['timeout'])->acceptJson()->post($config['api_url'], $body);
        } catch (ConnectionException $e) {
            Log::warning('VNPAY refund: không nhận được phản hồi', ['order' => $payment->order_code, 'refund' => $refundCode, 'error' => $e->getMessage()]);
            throw new PaymentException('Không nhận được phản hồi từ VNPAY — chưa rõ đã hoàn hay chưa. Kiểm tra trên cổng VNPAY trước khi làm lại.');
        }

        $json = (array) $response->json();

        if (! isset($json['vnp_ResponseCode'])) {
            Log::warning('VNPAY refund: phản hồi không hợp lệ', ['refund' => $refundCode, 'status' => $response->status(), 'body' => $json]);
            throw new PaymentException('VNPAY trả về phản hồi không hợp lệ — kiểm tra trên cổng VNPAY trước khi làm lại.');
        }

        $code = (string) $json['vnp_ResponseCode'];

        return new GatewayRefund(
            succeeded: $code === '00',
            transactionId: filled($json['vnp_TransactionNo'] ?? null) ? (string) $json['vnp_TransactionNo'] : null,
            resultCode: (int) $code,
            message: (string) ($json['vnp_Message'] ?? ''),
            raw: $json,
        );
    }

    /** Ký payload IPN/return — test và trang giả lập dùng để tạo IPN hợp lệ. */
    public function signNotification(array $payload): string
    {
        $data = collect($payload)
            ->filter(fn ($v, $k) => str_starts_with((string) $k, 'vnp_') && ! in_array($k, ['vnp_SecureHash', 'vnp_SecureHashType'], true))
            ->all();

        return $this->hmac($this->buildQuery($data));
    }

    public function tmnCode(): ?string
    {
        return $this->credentials()['tmn_code'];
    }

    // --------------------------------------------------------------------------------------

    /**
     * @param  array<string, mixed>  $source  payload gốc để lưu `raw`
     * @param  array<string, mixed>  $fields  payload đã quy `vnp_ResponseCode` về mã trạng thái giao dịch
     */
    private function toNotification(array $source, array $fields): GatewayNotification
    {
        $response = (string) ($fields['vnp_ResponseCode'] ?? '99');
        $status = (string) ($fields['vnp_TransactionStatus'] ?? $response);
        $success = $response === '00' && $status === '00';

        // Quy về chuẩn chung: 0 = thành công; còn lại lấy mã lỗi VNPAY (khác 0) để admin tra.
        $code = $success ? 0 : ((int) ($response !== '00' ? $response : $status) ?: 99);
        $transactionNo = (string) ($fields['vnp_TransactionNo'] ?? '');

        return new GatewayNotification(
            orderCode: (string) $fields['vnp_TxnRef'],
            amount: intdiv((int) $fields['vnp_Amount'], 100),
            transactionId: $transactionNo !== '' && $transactionNo !== '0' ? $transactionNo : null,
            resultCode: $code,
            message: $success ? 'Giao dịch thành công.' : (self::MESSAGES[$response] ?? self::MESSAGES[$status] ?? "Mã lỗi VNPAY {$response}/{$status}."),
            raw: $source,
            pending: ! $success && $status === self::STATUS_INCOMPLETE,
        );
    }

    /** Chữ ký phản hồi querydr — chuỗi giá trị nối `|` theo thứ tự tài liệu VNPAY. */
    private function verifyApiResponse(array $json): bool
    {
        if (! is_string($json['vnp_SecureHash'] ?? null)) {
            return false;
        }

        $raw = implode('|', array_map(fn ($k) => (string) ($json[$k] ?? ''), [
            'vnp_ResponseId', 'vnp_Command', 'vnp_ResponseCode', 'vnp_Message', 'vnp_TmnCode', 'vnp_TxnRef', 'vnp_Amount',
            'vnp_BankCode', 'vnp_PayDate', 'vnp_TransactionNo', 'vnp_TransactionType', 'vnp_TransactionStatus',
            'vnp_OrderInfo', 'vnp_PromotionCode', 'vnp_PromotionAmount',
        ]));

        return hash_equals($this->hmac($raw), strtolower($json['vnp_SecureHash']));
    }

    /** @param  array<string, mixed>  $params */
    private function buildQuery(array $params): string
    {
        ksort($params);

        return collect($params)
            ->map(fn ($v, $k) => urlencode((string) $k).'='.urlencode((string) $v))
            ->implode('&');
    }

    private function hmac(string $data): string
    {
        return hash_hmac('sha512', $data, (string) $this->credentials()['hash_secret']);
    }

    private function transactionDate(Payment $payment): string
    {
        return $payment->gateway_response['create']['vnp_CreateDate']
            ?? Carbon::parse($payment->created_at)->tz(self::TZ)->format('YmdHis');
    }

    private function serverIp(): string
    {
        return request()->server('SERVER_ADDR') ?: '127.0.0.1';
    }

    /** @return array<string, mixed> */
    protected function credentials(): array
    {
        return config('payment.vnpay');
    }
}
