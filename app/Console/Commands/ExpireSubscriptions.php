<?php

namespace App\Console\Commands;

use App\Services\SubscriptionService;
use Illuminate\Console\Command;

class ExpireSubscriptions extends Command
{
    protected $signature = 'subscriptions:expire {--pending-hours=24 : Huỷ đăng ký chờ thanh toán cũ hơn số giờ này}';

    protected $description = 'Đánh dấu gói hết hạn và huỷ đăng ký chờ thanh toán bị bỏ dở';

    public function handle(SubscriptionService $subscriptions): int
    {
        $expired = $subscriptions->expireDue();
        $cancelled = $subscriptions->cancelStalePending((int) $this->option('pending-hours'));

        $this->info("Hết hạn: {$expired} · Huỷ chờ thanh toán: {$cancelled}");

        return self::SUCCESS;
    }
}
