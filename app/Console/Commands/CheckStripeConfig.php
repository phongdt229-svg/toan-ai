<?php

namespace App\Console\Commands;

use App\Services\Payment\Gateways\StripeGateway;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

/** Kiểm tra cấu hình Stripe (ST-04): biến môi trường, URL webhook cần khai, gọi thử API đọc số dư. Không tạo giao dịch. */
class CheckStripeConfig extends Command
{
    protected $signature = 'payments:check-stripe';

    protected $description = 'Kiểm tra cấu hình Stripe (key, webhook secret, URL webhook cần khai)';

    public function handle(StripeGateway $stripe): int
    {
        $config = config('payment.stripe');

        $this->line('Webhook URL (khai trong Stripe Dashboard → Developers → Webhooks): '.route('api.payment.stripe.webhook'));
        $this->line('Sự kiện cần chọn: checkout.session.completed, checkout.session.async_payment_succeeded, checkout.session.async_payment_failed, checkout.session.expired');
        $this->line('Đang bật ở trang mua: '.(in_array('stripe', (array) config('payment.methods'), true) ? 'có' : 'chưa — thêm stripe vào PAYMENT_METHODS'));

        if (blank($config['secret_key'])) {
            $this->error('Thiếu STRIPE_SECRET_KEY trong .env.');

            return self::FAILURE;
        }

        if (blank($config['webhook_secret'])) {
            $this->warn('Thiếu STRIPE_WEBHOOK_SECRET — webhook sẽ bị từ chối hết (đơn chỉ được cập nhật khi trang đơn hỏi lại Stripe).');
        }

        try {
            $result = $stripe->probe();
        } catch (ConnectionException $e) {
            $this->error('Không kết nối được Stripe: '.$e->getMessage());

            return self::FAILURE;
        }

        $result['ok'] ? $this->info($result['message']) : $this->error($result['message']);

        if ($result['mode'] === 'live' && ! app()->environment('production')) {
            $this->warn('Đang dùng key LIVE ở môi trường '.app()->environment().' — giao dịch ở đây là tiền thật.');
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
