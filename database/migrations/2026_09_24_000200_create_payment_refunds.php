<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Hoàn tiền (chỉ hoàn toàn bộ đơn). Đơn hoàn xong chuyển sang status `refunded` để MỌI báo cáo
 * đang lọc `status = paid` tự loại nó khỏi doanh thu, không phải sửa từng truy vấn.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','paid','failed','cancelled','refunded') NOT NULL DEFAULT 'pending'");

        Schema::create('payment_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            // orderId gửi sang MoMo cho yêu cầu hoàn — phải khác mã đơn gốc và không trùng lần nào.
            $table->string('refund_code', 60)->unique();
            $table->decimal('amount', 12, 2);
            $table->string('reason', 191);
            // pending = đã gửi mà chưa biết kết quả (mất mạng...) — KHÔNG được gửi lại khi chưa kiểm tra bên MoMo.
            $table->enum('status', ['pending', 'succeeded', 'failed'])->default('pending');
            $table->string('gateway_transaction_id', 64)->nullable();
            $table->integer('gateway_result_code')->nullable();
            $table->string('gateway_message')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('refunded_at')->nullable();
            $table->timestamps();

            $table->index(['payment_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_refunds');
        DB::statement("UPDATE payments SET status = 'paid' WHERE status = 'refunded'");
        DB::statement("ALTER TABLE payments MODIFY status ENUM('pending','paid','failed','cancelled') NOT NULL DEFAULT 'pending'");
    }
};
