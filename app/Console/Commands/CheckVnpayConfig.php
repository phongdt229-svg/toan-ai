<?php

namespace App\Console\Commands;

use App\Services\Payment\Gateways\VnpayGateway;
use Illuminate\Console\Command;
use Illuminate\Http\Client\ConnectionException;

/**
 * Kiểm tra cấu hình VNPAY trước khi bật cho người dùng: đủ biến môi trường chưa, URL nào phải khai với VNPAY,
 * và gọi thử API truy vấn để xác nhận mã website + hash secret đúng. Không tạo giao dịch, không trừ tiền.
 */
class CheckVnpayConfig extends Command
{
    protected $signature = 'payments:check-vnpay';

    protected $description = 'Kiểm tra cấu hình VNPAY (biến môi trường, URL cần khai, gọi thử API sandbox/production)';

    public function handle(VnpayGateway $vnpay): int
    {
        $config = config('payment.vnpay');
        $missing = collect(['tmn_code' => 'VNPAY_TMN_CODE', 'hash_secret' => 'VNPAY_HASH_SECRET'])
            ->filter(fn ($env, $key) => blank($config[$key]))
            ->values();

        $this->line('Trang thanh toán:  '.$config['pay_url']);
        $this->line('API truy vấn:      '.$config['api_url']);
        $this->line('Return URL:        '.($config['return_url'] ?: route('payment.return.vnpay')));
        $this->line('IPN URL (khai trong trang quản trị merchant VNPAY): '.route('api.payment.vnpay.ipn'));
        $this->line('Đang bật ở trang mua: '.(in_array('vnpay', (array) config('payment.methods'), true) ? 'có' : 'chưa — thêm vnpay vào PAYMENT_METHODS'));

        if ($missing->isNotEmpty()) {
            $this->error('Thiếu: '.$missing->implode(', ').' trong .env.');

            return self::FAILURE;
        }

        if (! str_starts_with(route('api.payment.vnpay.ipn'), 'https://')) {
            $this->warn('IPN URL chưa phải https — VNPAY không gọi được về máy local. Dùng ngrok hoặc chạy trên server có HTTPS.');
        }

        try {
            $result = $vnpay->probe();
        } catch (ConnectionException $e) {
            $this->error('Không kết nối được VNPAY: '.$e->getMessage());

            return self::FAILURE;
        }

        $result['ok'] ? $this->info($result['message']) : $this->error($result['message']);

        if ($result['response_signature_valid'] === false) {
            $this->warn('Chữ ký phản hồi của VNPAY không khớp cách hệ thống kiểm — báo lại để chỉnh thứ tự trường (đối soát sẽ bỏ qua phản hồi).');
        }

        return $result['ok'] ? self::SUCCESS : self::FAILURE;
    }
}
