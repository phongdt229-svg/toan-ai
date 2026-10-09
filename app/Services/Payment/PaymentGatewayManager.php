<?php

namespace App\Services\Payment;

use App\Models\Payment;
use App\Services\Payment\Contracts\PaymentGatewayInterface;
use App\Services\Payment\Gateways\FakeMomoGateway;
use App\Services\Payment\Gateways\FakeStripeGateway;
use App\Services\Payment\Gateways\FakeVnpayGateway;
use App\Services\Payment\Gateways\MomoGateway;
use App\Services\Payment\Gateways\StripeGateway;
use App\Services\Payment\Gateways\VnpayGateway;

/**
 * Chọn cổng theo tên (`payments.method`). Đơn nào thì đối soát / hoàn tiền qua ĐÚNG cổng đã tạo đơn đó,
 * dù sau này cổng bị gỡ khỏi trang thanh toán.
 */
class PaymentGatewayManager
{
    /** @var array<string, class-string<PaymentGatewayInterface>> */
    private const REAL = [
        Payment::METHOD_MOMO => MomoGateway::class,
        Payment::METHOD_VNPAY => VnpayGateway::class,
        Payment::METHOD_STRIPE => StripeGateway::class,
    ];

    /** @var array<string, class-string<PaymentGatewayInterface>> */
    private const FAKE = [
        Payment::METHOD_MOMO => FakeMomoGateway::class,
        Payment::METHOD_VNPAY => FakeVnpayGateway::class,
        Payment::METHOD_STRIPE => FakeStripeGateway::class,
    ];

    /** @var array<string, PaymentGatewayInterface> */
    private array $resolved = [];

    /** @throws PaymentException */
    public function get(string $name): PaymentGatewayInterface
    {
        $map = $this->usesFake($name) ? self::FAKE : self::REAL;

        if (! isset($map[$name])) {
            throw new PaymentException('Phương thức thanh toán không hợp lệ.');
        }

        return $this->resolved[$map[$name]] ??= app($map[$name]);
    }

    /** Cổng hiện ở trang thanh toán, đúng thứ tự cấu hình; tên lạ trong .env bị bỏ qua. */
    public function enabled(): array
    {
        $methods = array_values(array_intersect((array) config('payment.methods'), array_keys(self::REAL)));

        return $methods ?: [Payment::METHOD_MOMO];
    }

    public function isEnabled(string $name): bool
    {
        return in_array($name, $this->enabled(), true);
    }

    public function default(): string
    {
        return $this->enabled()[0];
    }

    /**
     * Giả lập chỉ ở local/testing — production luôn gọi cổng thật dù .env ghi gì.
     * Cổng nằm trong `payment.live` thì chạy thật ngay cả khi đang giả lập: thử sandbox VNPAY
     * mà chưa có key MoMo thì không phải tắt giả lập cho cả hai.
     */
    public function usesFake(?string $name = null): bool
    {
        if ($name !== null && in_array($name, (array) config('payment.live'), true)) {
            return false;
        }

        return config('payment.gateway') === 'fake' && ! app()->environment('production');
    }
}
