<?php

namespace App\Services\Payment;

use App\Models\Package;
use App\Models\Payment;
use App\Models\PaymentRefund;
use App\Models\PaymentWebhookLog;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\PaymentRefunded;
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
        private readonly VoucherService $vouchers,
    ) {}

    /**
     * Tạo đơn + đăng ký chờ, lấy link thanh toán.
     *
     * `$voucherCode` là chuỗi người dùng gõ — số tiền giảm được TÍNH LẠI ở đây, không nhận từ client.
     *
     * @throws PaymentException
     */
    public function checkout(User $payer, User $beneficiary, Package $package, ?string $ip = null, ?string $voucherCode = null): Payment
    {
        try {
            $quote = $voucherCode !== null && trim($voucherCode) !== ''
                ? $this->vouchers->quote($voucherCode, $package, $payer)
                : null;
        } catch (VoucherException $e) {
            throw new PaymentException($e->getMessage(), previous: $e);
        }

        $amount = $quote?->payable ?? (float) $package->price;
        $discount = $quote?->discount ?? 0.0;

        // Bấm "Thanh toán" nhiều lần → dùng lại đơn còn hạn, không đẻ thêm đơn rác.
        // Phải khớp cả mã đang dùng: gỡ mã ra mà vẫn nhận lại đơn đã giảm là cho không tiền.
        $reusable = Payment::query()
            ->where('user_id', $payer->id)
            ->where('package_id', $package->id)
            ->where('status', Payment::STATUS_PENDING)
            ->where('expires_at', '>', now()->addMinutes(5))
            ->where('amount', $amount) // admin vừa đổi giá → không dùng lại đơn giá cũ
            ->where('voucher_id', $quote?->voucher->id)
            ->whereNotNull('pay_url')
            ->whereHas('subscription', fn ($q) => $q->where('user_id', $beneficiary->id)->where('status', Subscription::STATUS_PENDING))
            ->latest('id')
            ->first();

        if ($reusable) {
            return $reusable;
        }

        try {
            $payment = DB::transaction(function () use ($payer, $beneficiary, $package, $ip, $quote, $amount, $discount) {
                $subscription = $this->subscriptions->createPending($beneficiary, $package, $payer);

                $payment = Payment::create([
                    'order_code' => $this->newOrderCode(),
                    'user_id' => $payer->id,
                    'package_id' => $package->id,
                    'voucher_id' => $quote?->voucher->id,
                    'subscription_id' => $subscription->id,
                    'amount' => $amount,   // ← giá LẤY TỪ DB, trừ đi phần giảm tính ở server
                    'discount_amount' => $discount,
                    'currency' => $package->currency,
                    'method' => $quote?->isFree() ? Payment::METHOD_VOUCHER : $this->gateway->name(),
                    'status' => Payment::STATUS_PENDING,
                    'expires_at' => now()->addMinutes(config('payment.pending_expire_minutes')),
                    'client_ip' => $ip,
                ]);

                // Giữ chỗ NGAY khi tạo đơn, không đợi tới lúc trả tiền: mã còn 1 lượt mà 10 người
                // cùng mang sang MoMo thì 9 người trả tiền xong mới biết mình trượt.
                if ($quote) {
                    $this->vouchers->hold($quote->voucher, $package, $payer, $payment);
                }

                return $payment;
            });
        } catch (VoucherException $e) {
            throw new PaymentException($e->getMessage(), previous: $e);
        } catch (RuntimeException $e) {
            throw new PaymentException($e->getMessage(), previous: $e);
        }

        // Mã giảm 100%: MoMo không nhận đơn 0đ → cấp gói thẳng, vẫn để lại dòng trong sổ.
        if ($quote?->isFree()) {
            return $this->settleFreeOrder($payment);
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

    /**
     * Hoàn tiền một đơn đã thanh toán — toàn bộ (`$amount = null`) hoặc một phần, do admin bấm.
     *
     * Hoàn một phần: đơn vẫn `paid`, gói giữ nguyên (coi như bồi hoàn); chỉ khi tổng đã hoàn chạm đúng số tiền
     * thực trả thì đơn mới sang `refunded` và gói bị thu hồi.
     *
     * Thứ tự cố ý: ghi yêu cầu `pending` TRƯỚC khi gọi cổng. Nếu cổng nhận mà ta mất kết nối, dòng pending còn đó
     * và chặn admin bấm lần hai — hoàn hai lần là mất tiền thật. Chỉ khi cổng báo rõ thành công mới cộng vào
     * `refunded_amount`.
     *
     * @throws PaymentException
     */
    public function refund(Payment $payment, User $admin, string $reason, ?int $amount = null): PaymentRefund
    {
        $refund = DB::transaction(function () use ($payment, $admin, $reason, $amount) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if (! $payment->isPaid()) {
                throw new PaymentException('Chỉ hoàn được đơn đã thanh toán thành công.');
            }

            if ($payment->method === Payment::METHOD_VOUCHER || $payment->amountInt() <= 0) {
                throw new PaymentException('Đơn 0đ (mã giảm 100%) không có tiền để hoàn — hãy huỷ đăng ký ở trang Đăng ký gói.');
            }

            if ($payment->refunds()->where('status', PaymentRefund::STATUS_PENDING)->exists()) {
                throw new PaymentException('Đơn này có yêu cầu hoàn tiền chưa rõ kết quả. Kiểm tra trên cổng MoMo trước khi làm lại.');
            }

            $refundable = $payment->refundableInt();
            $amount ??= $refundable;

            if ($amount < 1 || $amount > $refundable) {
                throw new PaymentException('Số tiền hoàn phải từ 1₫ đến '.number_format($refundable, 0, ',', '.').'₫ (phần còn lại của đơn).');
            }

            return $payment->refunds()->create([
                'refund_code' => 'RF'.$payment->order_code.strtoupper(Str::random(4)),
                'amount' => $amount,
                'reason' => $reason,
                'status' => PaymentRefund::STATUS_PENDING,
                'requested_by' => $admin->id,
            ]);
        });

        $payment->refresh();

        // Gọi mạng NGOÀI transaction: không giữ khoá dòng trong lúc chờ MoMo.
        $result = $this->gateway->refund($payment, $refund->refund_code, (int) $refund->amount, $reason);

        if (! $result->succeeded) {
            $refund->update([
                'status' => PaymentRefund::STATUS_FAILED,
                'gateway_result_code' => $result->resultCode,
                'gateway_message' => Str::limit($result->message, 180),
            ]);

            $this->audit->log('payment.refund_failed', $payment, null, ['refund_code' => $refund->refund_code, 'result' => $result->resultCode]);

            throw new PaymentException('MoMo từ chối hoàn tiền: '.($result->message ?: "mã {$result->resultCode}").'.');
        }

        DB::transaction(function () use ($payment, $refund, $result, $admin, $reason) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            $refund->update([
                'status' => PaymentRefund::STATUS_SUCCEEDED,
                'gateway_transaction_id' => $result->transactionId,
                'gateway_result_code' => $result->resultCode,
                'gateway_message' => Str::limit($result->message, 180),
                'refunded_at' => now(),
            ]);

            // Tính lại từ bảng hoàn tiền thay vì cộng dồn — sai một lần là lệch mãi.
            $total = (float) $payment->refunds()->where('status', PaymentRefund::STATUS_SUCCEEDED)->sum('amount');
            $full = (int) round($total) >= $payment->amountInt();

            $payment->update(['refunded_amount' => $total, 'status' => $full ? Payment::STATUS_REFUNDED : Payment::STATUS_PAID]);

            // Hoàn đủ tiền thì gói không còn lý do tồn tại; hoàn một phần thì giữ gói.
            if ($full) {
                $subscription = Subscription::lockForUpdate()->find($payment->subscription_id);
                if ($subscription && in_array($subscription->status, [Subscription::STATUS_ACTIVE, Subscription::STATUS_PENDING], true)) {
                    $this->subscriptions->cancel($subscription, $admin, "Hoàn tiền: {$reason}");
                }
            }

            $this->audit->log('payment.refunded', $payment, ['status' => Payment::STATUS_PAID], [
                'refund_code' => $refund->refund_code,
                'amount' => (int) $refund->amount,
                'total_refunded' => (int) round($total),
                'full' => $full,
                'reason' => $reason,
            ]);

            DB::afterCommit(fn () => $payment->user->notify(new PaymentRefunded($payment, $refund)));
        });

        return $refund->refresh();
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
     * Đơn 0đ do mã giảm 100%. Không có cổng thanh toán nào tham gia, nên kích hoạt tại chỗ —
     * vẫn đi qua `SubscriptionService::activate()` và vẫn ghi audit như mọi đơn khác.
     */
    private function settleFreeOrder(Payment $payment): Payment
    {
        return DB::transaction(function () use ($payment) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->firstOrFail();

            if ($payment->isPaid()) {
                return $payment;
            }

            $payment->update([
                'status' => Payment::STATUS_PAID,
                'paid_at' => now(),
                'gateway_message' => 'Miễn phí bằng mã giảm giá',
            ]);

            $subscription = $this->subscriptions->activate(
                Subscription::lockForUpdate()->findOrFail($payment->subscription_id)
            );

            $this->vouchers->markRedeemed($payment);

            $this->audit->log('payment.paid', $payment, null, [
                'order_code' => $payment->order_code,
                'amount' => 0,
                'voucher_id' => $payment->voucher_id,
                'subscription_id' => $subscription->id,
            ]);

            $payment->load('user', 'package', 'subscription.user');
            DB::afterCommit(fn () => $payment->user->notify(new PaymentSucceeded($payment)));

            return $payment;
        });
    }

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

            // 5. Idempotency: đã xử lý xong thì thôi. Đơn đã hoàn tiền cũng tính là đã xử lý — IPN gửi lại
            // mà rơi xuống dưới sẽ kích hoạt lại gói vừa bị thu hồi.
            if ($payment->isPaid() || $payment->isRefunded()) {
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
            $this->vouchers->markRedeemed($payment);

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

            // Trả lượt mã về kho cho người khác dùng.
            $this->vouchers->release($payment);

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
