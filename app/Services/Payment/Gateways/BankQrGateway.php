<?php

namespace App\Services\Payment\Gateways;

use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\GatewayCheckout;
use App\Services\Payment\GatewayNotification;
use App\Services\Payment\GatewayRefund;
use App\Services\Payment\PaymentException;
use App\Support\QrCode;
use App\Support\VietQr;

/**
 * Chuyển khoản ngân hàng quét mã VietQR, vào thẳng tài khoản cấu hình ở config/payment.php → bank_qr.
 *
 * Không có "cổng" nào đứng giữa: tiền về tài khoản, rồi dịch vụ biến động số dư (định dạng webhook SePay)
 * báo lại kèm khoá bí mật. Nội dung chuyển khoản = mã đơn, nên khớp đơn bằng cách tìm mã đơn trong nội dung.
 * Chưa có webhook thì quản trị xác nhận tay (PaymentService::confirmBankTransfer).
 */
class BankQrGateway implements PaymentGatewayInterface
{
    /** Mã đơn trong nội dung CK: TOANAI + 12 số ngày giờ + 6 ký tự — app ngân hàng hay viết hoa / chèn chữ quanh nó. */
    private const ORDER_CODE_PATTERN = '/TOANAI\d{12}[A-Z0-9]{6}/i';

    public function name(): string
    {
        return Payment::METHOD_BANK_QR;
    }

    public function isConfigured(): bool
    {
        return filled(config('payment.bank_qr.bank_bin')) && filled(config('payment.bank_qr.account_no'));
    }

    public function createPayment(Payment $payment, string $orderInfo): GatewayCheckout
    {
        if (! $this->isConfigured()) {
            throw new PaymentException('Chuyển khoản QR chưa được cấu hình tài khoản nhận. Vui lòng chọn cách thanh toán khác.');
        }

        // Chụp lại tài khoản nhận lúc tạo đơn: đổi tài khoản trong .env sau đó không làm khách đang chuyển bị lạc tiền.
        return new GatewayCheckout(
            payUrl: route('payment.show', $payment),
            requestId: $payment->order_code,
            raw: [
                'bank_bin' => (string) config('payment.bank_qr.bank_bin'),
                'bank_name' => (string) config('payment.bank_qr.bank_name'),
                'account_no' => (string) config('payment.bank_qr.account_no'),
                'account_name' => (string) config('payment.bank_qr.account_name'),
                'content' => VietQr::cleanContent($payment->order_code),
            ],
        );
    }

    /**
     * Thông tin hiện ở trang thanh toán: QR + các dòng để khách gõ tay nếu không quét được.
     *
     * @return array{bank_name: string, account_no: string, account_name: string, amount: int, content: string, qr_svg: string}
     */
    public function instructions(Payment $payment): array
    {
        $snapshot = (array) (($payment->gateway_response ?? [])['create'] ?? []);
        $bin = (string) ($snapshot['bank_bin'] ?? config('payment.bank_qr.bank_bin'));
        $accountNo = (string) ($snapshot['account_no'] ?? config('payment.bank_qr.account_no'));
        $content = (string) ($snapshot['content'] ?? VietQr::cleanContent($payment->order_code));

        return [
            'bank_name' => (string) ($snapshot['bank_name'] ?? config('payment.bank_qr.bank_name')),
            'account_no' => $accountNo,
            'account_name' => (string) ($snapshot['account_name'] ?? config('payment.bank_qr.account_name')),
            'amount' => $payment->amountInt(),
            'content' => $content,
            'qr_svg' => QrCode::svg(VietQr::payload($bin, $accountNo, $payment->amountInt(), $content), 240),
        ];
    }

    /**
     * Webhook không có chữ ký trên thân, chỉ có khoá bí mật trong header — đọc từ request hiện tại vì
     * header này KHÔNG được ghi vào log webhook (log chỉ giữ payload).
     */
    public function verifyNotification(array $payload): bool
    {
        $secret = (string) config('payment.bank_qr.webhook_secret');
        if ($secret === '') {
            return false; // chưa cấu hình = không tin bất kỳ ai
        }

        $header = (string) request()->header('Authorization', '');
        $given = preg_replace('/^(Apikey|Bearer)\s+/i', '', trim($header)) ?? '';

        return $given !== '' && hash_equals($secret, $given);
    }

    public function parseNotification(array $payload): GatewayNotification
    {
        $incoming = strtolower((string) ($payload['transferType'] ?? 'in')) === 'in';
        $account = preg_replace('/\D/', '', (string) ($payload['accountNumber'] ?? '')) ?? '';
        $ours = preg_replace('/\D/', '', (string) config('payment.bank_qr.account_no')) ?? '';

        // Tiền ra, hoặc báo về một tài khoản khác của cùng dịch vụ → bỏ qua (pending), không đánh trượt đơn.
        $ignore = ! $incoming || ($account !== '' && $ours !== '' && $account !== $ours);

        return new GatewayNotification(
            orderCode: (string) $this->notificationOrderCode($payload),
            amount: (int) round((float) ($payload['transferAmount'] ?? 0)),
            transactionId: ($ref = (string) ($payload['referenceCode'] ?? $payload['id'] ?? '')) !== '' ? substr($ref, 0, 100) : null,
            resultCode: $ignore ? 1 : 0,
            message: $ignore ? 'Không phải tiền vào tài khoản nhận' : 'Đã nhận chuyển khoản',
            raw: $payload,
            pending: $ignore,
        );
    }

    public function notificationOrderCode(array $payload): ?string
    {
        foreach (['code', 'content', 'description'] as $key) {
            if (preg_match(self::ORDER_CODE_PATTERN, (string) ($payload[$key] ?? ''), $m)) {
                return strtoupper($m[0]);
            }
        }

        return null;
    }

    /** Ngân hàng không có API tra cứu ở đây — trạng thái chỉ đổi qua webhook hoặc quản trị xác nhận. */
    public function queryStatus(Payment $payment): ?GatewayNotification
    {
        return null;
    }

    /**
     * Không có cổng để gọi hoàn: quản trị tự chuyển khoản trả khách rồi bấm Hoàn tiền để ghi sổ
     * (trang quản trị nhắc rõ điều này trước khi bấm).
     */
    public function refund(Payment $payment, string $refundCode, int $amount, string $reason): GatewayRefund
    {
        return new GatewayRefund(true, null, 0, 'Hoàn thủ công: quản trị đã chuyển khoản trả lại khách.');
    }
}
