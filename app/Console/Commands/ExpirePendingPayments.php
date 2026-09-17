<?php

namespace App\Console\Commands;

use App\Services\Payment\PaymentService;
use Illuminate\Console\Command;

class ExpirePendingPayments extends Command
{
    protected $signature = 'payments:expire-pending';

    protected $description = 'Đối soát rồi huỷ các đơn thanh toán quá hạn chưa trả';

    public function handle(PaymentService $payments): int
    {
        $this->info('Đã huỷ '.$payments->expireStale().' đơn quá hạn.');

        return self::SUCCESS;
    }
}
