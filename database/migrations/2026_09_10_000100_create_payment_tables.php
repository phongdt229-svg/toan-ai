<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            // Mã đơn gửi sang cổng thanh toán (MoMo orderId) — tra cứu IPN theo cột này.
            $table->string('order_code', 50)->unique();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete(); // người trả tiền
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 12, 2);
            $table->string('currency', 3)->default('VND');
            $table->string('method', 20)->default('momo');
            $table->enum('status', ['pending', 'paid', 'failed', 'cancelled'])->default('pending');
            $table->string('gateway_request_id', 64)->nullable();
            $table->string('gateway_transaction_id', 64)->nullable()->unique();
            $table->integer('gateway_result_code')->nullable();
            $table->string('gateway_message')->nullable();
            $table->text('pay_url')->nullable();
            $table->json('gateway_response')->nullable();
            // IPN hợp lệ chữ ký nhưng số tiền lệch… — giữ lại để admin xử lý tay, không tự cấp gói.
            $table->string('flag_reason')->nullable();
            $table->dateTime('paid_at')->nullable();
            $table->dateTime('expires_at')->nullable();
            $table->string('client_ip', 45)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        Schema::create('payment_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 20); // momo | momo-query
            $table->string('order_code', 50)->nullable()->index();
            $table->boolean('signature_valid')->default(false);
            $table->json('payload');
            $table->json('headers')->nullable();
            // processed | duplicate | invalid_signature | not_found | amount_mismatch | payment_failed | error
            $table->string('result', 30)->nullable();
            $table->string('message')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->dateTime('processed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_webhook_logs');
        Schema::dropIfExists('payments');
    }
};
