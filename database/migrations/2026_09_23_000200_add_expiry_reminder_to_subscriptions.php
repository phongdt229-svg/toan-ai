<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nhắc gói sắp hết hạn: giữ ngưỡng (7/3/1 ngày) đã nhắc gần nhất để không gửi trùng.
 * `SubscriptionService::activate()` đặt lại null khi gia hạn để lần sau còn nhắc tiếp.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->unsignedTinyInteger('expiry_reminded_days')->nullable()->after('cancel_reason');
        });
    }

    public function down(): void
    {
        Schema::table('subscriptions', function (Blueprint $table) {
            $table->dropColumn('expiry_reminded_days');
        });
    }
};
