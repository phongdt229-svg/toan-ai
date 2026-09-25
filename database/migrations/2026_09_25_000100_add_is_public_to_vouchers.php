<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Mã nào được đem khoe ở trang Gói học.
 *
 * Mặc định FALSE: phần lớn mã là của một chiến dịch riêng (gửi cho trường, cho nhóm thử nghiệm),
 * đem hiện công khai là ai cũng dùng được và chiến dịch mất ý nghĩa. Muốn khoe thì phải bật tay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->boolean('is_public')->default(false)->after('is_active');
        });
    }

    public function down(): void
    {
        Schema::table('vouchers', function (Blueprint $table) {
            $table->dropColumn('is_public');
        });
    }
};
