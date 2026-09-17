<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §18–19 — Gói học & đăng ký gói. Giá và quyền lợi nằm trong DB, không hard-code.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->enum('tier', ['free', 'pro', 'premium']);
            // VND lưu decimal để không lệch khi cộng trừ; MoMo nhận số nguyên (Phase 9).
            $table->decimal('price', 12, 2)->default(0);
            $table->string('currency', 3)->default('VND');
            // null = không hết hạn (gói Free).
            $table->unsignedSmallInteger('duration_days')->nullable();
            $table->string('description', 500)->nullable();
            // Gói mặc định cho người chưa mua gì — không được xoá, chỉ sửa.
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_highlighted')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['is_active', 'sort_order']);
        });

        Schema::create('package_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            // Khoá máy đọc, vd ai.daily_requests. Nhãn là chữ hiển thị trên bảng giá.
            $table->string('key', 100);
            $table->string('label', 191);
            // Cho tính năng bật/tắt: '1' / '0'. Cho giới hạn: dùng limit_value (null = không giới hạn).
            $table->string('value', 100)->nullable();
            $table->unsignedInteger('limit_value')->nullable();
            $table->boolean('show_on_pricing')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['package_id', 'key']);
        });

        Schema::create('subscriptions', function (Blueprint $table) {
            $table->id();
            // Người được dùng gói (học sinh). Phụ huynh mua cho con thì purchased_by là phụ huynh.
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('purchased_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('package_id')->constrained()->restrictOnDelete();
            $table->enum('status', ['pending', 'active', 'expired', 'cancelled'])->default('pending');
            // Giá tại thời điểm mua — admin đổi giá sau không làm sai lịch sử.
            $table->decimal('price_paid', 12, 2)->default(0);
            $table->unsignedSmallInteger('duration_days')->nullable();
            // Mua nối tiếp gói đang dùng → starts_at ở tương lai (cộng dồn thời hạn).
            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();
            $table->dateTime('activated_at')->nullable();
            $table->dateTime('cancelled_at')->nullable();
            $table->string('cancel_reason', 191)->nullable();
            // manual: admin cấp tay (khuyến mãi, xử lý sự cố). payment: qua thanh toán (Phase 9).
            $table->enum('source', ['payment', 'manual'])->default('payment');
            $table->timestamps();

            $table->index(['user_id', 'status', 'ends_at']);
            $table->index(['status', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('package_features');
        Schema::dropIfExists('packages');
    }
};
