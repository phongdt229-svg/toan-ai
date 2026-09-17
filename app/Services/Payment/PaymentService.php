<?php

namespace App\Services\Payment;

use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentWebhookLog;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentSucceeded;
use App\Services\AuditLogger;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\SubscriptionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

/**
 * Luồng thanh toán (PROJECT_PLAN.md §8, spec §19–25).
 *
 * Nguyên tắc: số tiền lấy từ DB; chỉ IPN có chữ ký hợp lệ (hoặc truy vấn trực tiếp cổng) mới kích hoạt gói;
 * return URL chỉ để hiển thị; xử lý IPN idempotent.
 */
class PaymentService
{
    public function __construct(
        private readonly PaymentGatewayInterface $gateway,
        private readonly SubscriptionService $subscriptions,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Tạo đơn + đăng ký chờ, lấy link thanh toán.
     *
     * @throws PaymentException
     */
    public function checkout(User $payer, User $beneficiary, Package $package, ?string $ip = null): Payment
    {
        // Bấm "Thanh toán" nhiều lần → dùng lại đơn còn hạn, không đẻ thêm đơn rác.
        $reusable = Payment::query()
            ->where('user_id', $payer->id)
            ->where('package_id', $package->id)
            ->where('status', Payment::STATUS_PENDING)
            ->where('expires_at', '>', now()->addMinutes(5))
            ->where('amount', $package->price) // admin vừa đổi giá → không dùng lại đơn giá cũ
            ->whereNotNull('pay_url')
            ->whereHas('subscription', fn ($q) => $q->where('user_id', $beneficiary->id)->where('status', Subscription::STATUS_PENDING))
            ->latest('id')
            ->first();

        if ($reusable) {
            return $reusable;
        }

        try {
            $payment = DB::transaction(function () use ($payer, $beneficiary, $package, $ip) {
                $subscription = $this->subscriptions->createPending($beneficiary, $package, $payer);

                return Payment::create([
                    'order_code' => $this->newOrderCode(),
                    'user_id' => $payer->id,
                    'package_id' => $package->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $package->price, // ← giá LẤY TỪ DB
                    'currency' => $package->currency,
                    'method' => $this->gateway->name(),
                    'status' => Payment::STATUS_PENDING,
                    'expires_at' => now()->addMinutes(config('payment.pending_expire_minutes')),
                    'client_ip' => $ip,
                ]);
            });
        } catch (RuntimeException $e) {
            throw new PaymentException($e->getMessage(), previous: $e);
        }

        try {
            // Gọi cổng NGOÀI transaction: không giữ khoá DB trong lúc chờ mạng.
            $checkout = $this->gateway->createPayment($payment, $this->orderInfo($package, $beneficiary));
        } catch (PaymentException $e) {
            $this->markFailed($payment, null, $e->getMessage());

            throw $e;
        }

        $payment->update([
            'pay_url' => $checkout->payUrl,
            'gateway_request_id' => $checkout->requestId,
            'gateway_response' => ['create' => $checkout->raw],
        ]);

        return $payment;
    }

    /**
     * Xử lý IPN. Luôn trả kết quả dạng chuỗi để controller log/test — HTTP luôn 204,
     * không tiết lộ cho bên gọi lý do từ chối.
     *
     * @param  array<string, mixed>  $payload
     * @param  array<string, mixed>  $headers
     */
    public function handleNotification(array $payload, ?string $ip = null, array $headers = []): string
    {
        // 1. Ghi log payload thô TRƯỚC khi xử lý — kể cả khi các bước sau nổ.
        $log = PaymentWebhookLog::create([
            'provider' => $this->gateway->name(),
            'order_code' => is_scalar($payload['orderId'] ?? null) ? Str::limit((string) $payload['orderId'], 50, '') : null,
            'payload' => $payload,
            'headers' => $headers,
            'ip_address' => $ip,
        ]);

        try {
            // 2. Chữ ký
            if (! $this->gateway->verifyNotification($payload)) {
                return $this->finish($log, PaymentWebhookLog::RESULT_INVALID_SIGNATURE);
            }

            $log->update(['signature_valid' => true]);

            return $this->finish($log, ...$this->apply($this->gateway->parseNotification($payload)));
        } catch (Throwable $e) {
            Log::error('Xử lý IPN lỗi', ['log_id' => $log->id, 'error' => $e->getMessage()]);

            return $this->finish($log, PaymentWebhookLog::RESULT_ERROR, Str::limit($e->getMessage(), 180));
        }
    }

    /**
     * IPN không tới được (localhost, MoMo lỗi mạng) → hỏi thẳng cổng. Kết quả truy vấn đi server → cổng
     * qua HTTPS nên tin được như IPN có chữ ký.
     */
    public function reconcile(Payment $payment): Payment
    {
        if (! $payment->isPending()) {
            return $payment;
        }

        $result = $this->gateway->queryStatus($payment);

        if ($result) {
            $log = PaymentWebhookLog::create([
                'provider' => $this->gateway->name().'-query',
                'order_code' => $payment->order_code,
                'signature_valid' => true,
                'payload' => $result->raw,
            ]);

            $this->finish($log, ...$this->apply($result));
        }

        return $payment->refresh();
    }

    /** Đơn chờ quá hạn → huỷ cùng đăng ký chờ. Trước khi huỷ hỏi lại cổng một lần để không huỷ nhầm đơn đã trả. */
    public function expireStale(): int
    {
        $count = 0;

        Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->where('expires_at', '<=', now())
            ->each(function (Payment $payment) use (&$count) {
                if ($this->reconcile($payment)->isPending()) {
                    $this->markFailed($payment, null, 'Hết thời gian thanh toán', Payment::STATUS_CANCELLED);
                    $count++;
                }
            });

        return $count;
    }

    // --------------------------------------------------------------------------------------

    /**
     * Bước 3–6 của §8, dùng chung cho IPN và truy vấn.
     *
     * @return array{0: string, 1?: string}
     */
    private function apply(GatewayNotification $n): array
    {
        return DB::transaction(function () use ($n) {
            // 3. Tìm đơn — khoá dòng để hai IPN đến cùng lúc không cùng cấp gói.
            $payment = Payment::where('order_code', $n->orderCode)->lockForUpdate()->first();

            if (! $payment) {
                return [PaymentWebhookLog::RESULT_NOT_FOUND];
            }

            // 5. Idempotency: đã xử lý xong thì thôi.
            if ($payment->isPaid()) {
                return [PaymentWebhookLog::RESULT_DUPLICATE];
            }

            // 4. Số tiền (số nguyên VND).
            if ($n->amount !== $payment->amountInt()) {
                $payment->update(['flag_reason' => "Số tiền IPN {$n->amount} ≠ đơn {$payment->amountInt()}"]);
                Log::warning('IPN lệch số tiền', ['order' => $payment->order_code, 'ipn' => $n->amount, 'expected' => $payment->amountInt()]);

                return [PaymentWebhookLog::RESULT_AMOUNT_MISMATCH];
            }

            if ($n->isPending()) {
                return [PaymentWebhookLog::RESULT_PENDING];
            }

            if (! $n->isSuccess()) {
                // Đơn đã huỷ do hết hạn mà cổng vẫn báo thất bại → giữ nguyên.
                if ($payment->isPending()) {
                    $this->markFailed($payment, $n);
                }

                return [PaymentWebhookLog::RESULT_PAYMENT_FAILED, "resultCode {$n->resultCode}"];
            }

            // Tiền đã trừ nhưng đơn bị huỷ do hết hạn (người dùng trả sát giờ) → vẫn phải cấp gói.
            $subscription = Subscription::lockForUpdate()->findOrFail($payment->subscription_id);

            if ($subscription->status === Subscription::STATUS_CANCELLED) {
                $subscription->update(['status' => Subscription::STATUS_PENDING, 'cancelled_at' => null, 'cancel_reason' => null]);
            }

            // 6. Cập nhật đơn + kích hoạt gói trong cùng transaction.
            $payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'gateway_transaction_id' => $n->transactionId,
                'gateway_result_code' => $n->resultCode,
                'gateway_message' => Str::limit($n->message, 180),
                'flag_reason' => null,
                'gateway_response' => [...(array) $payment->gateway_response, 'notification' => $n->raw],
            ]);

            $subscription = $this->subscriptions->activate($subscription);

            // 7. Audit + thông báo (gửi sau commit).
            $this->audit->log('payment.paid', $payment, null, [
                'order_code' => $payment->order_code,
                'amount' => $payment->amountInt(),
                'transaction_id' => $n->transactionId,
                'subscription_id' => $subscription->id,
            ]);

            $payment->load('user', 'package', 'subscription.user');
            DB::afterCommit(fn () => $payment->user->notify(new PaymentSucceeded($payment)));

            return [PaymentWebhookLog::RESULT_PROCESSED];
        });
    }

    private function markFailed(Payment $payment, ?GatewayNotification $n, ?string $message = null, string $status = Payment::STATUS_FAILED): void
    {
        DB::transaction(function () use ($payment, $n, $message, $status) {
            $payment->update([
                'status' => $status,
                'gateway_result_code' => $n?->resultCode,
                'gateway_message' => Str::limit($n?->message ?? $message ?? '', 180),
            ]);

            Subscription::whereKey($payment->subscription_id)
                ->where('status', Subscription::STATUS_PENDING)
                ->update([
                    'status' => Subscription::STATUS_CANCELLED,
                    'cancelled_at' => now(),
                    'cancel_reason' => 'Thanh toán không thành công',
                ]);
        });
    }

    private function finish(PaymentWebhookLog $log, string $result, ?string $message = null): string
    {
        $log->update(['result' => $result, 'message' => $message, 'processed_at' => now()]);

        return $result;
    }

    /** Mã đơn: TOANAI + ngày giờ + ngẫu nhiên — không đoán được, không lộ id nội bộ. */
    private function newOrderCode(): string
    {
        do {
            $code = 'TOANAI'.now()->format('ymdHis').strtoupper(Str::random(6));
        } while (Payment::where('order_code', $code)->exists());

        return $code;
    }

    private function orderInfo(Package $package, User $beneficiary): string
    {
        // MoMo hiển thị chuỗi này cho người trả tiền — ASCII để tránh lỗi font trên app cũ.
        return Str::limit(Str::ascii("TOAN AI - {$package->name} cho {$beneficiary->name}"), 190, '');
    }
}
