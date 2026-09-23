<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hoàn một phần: đơn vẫn `paid` (gói còn dùng) nhưng doanh thu ròng = amount − refunded_amount.
 * Cột đếm sẵn được phép ở đây vì mọi thay đổi đi qua đúng một chỗ (`PaymentService::refund`) và tính lại
 * từ bảng `payment_refunds`; khác với lượt dùng voucher, hoàn tiền không có chuyện "trả lại lượt".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->decimal('refunded_amount', 12, 2)->default(0)->after('discount_amount');
        });

        // Đơn đã hoàn toàn bộ trước khi có cột này: điền số tiền đã hoàn để báo cáo ròng không lệch.
        DB::statement("UPDATE payments SET refunded_amount = amount WHERE status = 'refunded'");
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('refunded_amount');
        });
    }
};
