<?php

namespace App\Console\Commands;

use App\Services\Auth\AccountDeletionService;
use Illuminate\Console\Command;

class PurgeDeletedAccounts extends Command
{
    protected $signature = 'accounts:purge {--days= : Số ngày giữ trước khi ẩn danh (mặc định 30)}';

    protected $description = 'Ẩn danh vĩnh viễn các tài khoản đã yêu cầu xoá quá thời gian giữ';

    public function handle(AccountDeletionService $deletion): int
    {
        $days = $this->option('days') !== null ? (int) $this->option('days') : null;

        $this->info('Đã ẩn danh '.$deletion->purgeDue($days).' tài khoản.');

        return self::SUCCESS;
    }
}
