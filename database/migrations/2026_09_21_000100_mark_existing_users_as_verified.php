<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bật xác thực email cho tài khoản MỚI. Tài khoản đã dùng hệ thống từ trước không có lỗi gì
 * để bị chặn giữa chừng → đánh dấu là đã xác thực ngay khi tính năng lên.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Không khôi phục: không biết tài khoản nào vốn chưa xác thực trước khi chạy migration này.
    }
};
