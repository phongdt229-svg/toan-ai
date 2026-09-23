<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Minishlink\WebPush\VAPID;
use Throwable;

/** Sinh cặp khoá VAPID cho Web Push. Chạy một lần, dán kết quả vào .env. */
class GenerateVapidKeys extends Command
{
    protected $signature = 'push:keys';

    protected $description = 'Sinh cặp khoá VAPID cho thông báo đẩy';

    public function handle(): int
    {
        try {
            $keys = VAPID::createVapidKeys();
        } catch (Throwable $e) {
            // XAMPP trên Windows thường không đặt OPENSSL_CONF nên openssl không tạo được khoá EC.
            $this->error('Không sinh được khoá: '.$e->getMessage());
            $this->newLine();
            $this->comment('Windows/XAMPP: chỉ cho openssl thấy file cấu hình rồi chạy lại:');
            $this->line('  OPENSSL_CONF=C:/xampp/apache/conf/openssl.cnf php artisan push:keys');

            return self::FAILURE;
        }

        $this->info('Dán hai dòng này vào .env (KHÔNG commit khoá riêng):');
        $this->newLine();
        $this->line('VAPID_PUBLIC_KEY='.$keys['publicKey']);
        $this->line('VAPID_PRIVATE_KEY='.$keys['privateKey']);
        $this->newLine();
        $this->comment('Đổi khoá là mọi thiết bị đã đăng ký phải đăng ký lại — chỉ đổi khi lộ khoá.');

        return self::SUCCESS;
    }
}
