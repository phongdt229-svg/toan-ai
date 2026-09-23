<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Voucher giảm giá (PROJECT_PLAN.md §8b).
 *
 * Số lượt đã dùng KHÔNG lưu thành cột đếm sẵn mà đếm từ `voucher_redemptions` —
 * đơn huỷ/hết hạn phải trả lại lượt, cột đếm sẵn rất dễ lệch.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('vouchers', function (Blueprint $table) {
            $table->id();
            // Lưu in hoa; người dùng gõ chữ thường vẫn nhận (so sánh sau khi upper).
            $table->string('code', 32)->unique();
            $table->string('description', 191)->nullable();

            $table->enum('type', ['percent', 'fixed']);
            $table->decimal('value', 12, 2);
            // Trần giảm cho loại percent: "giảm 50% tối đa 100k".
            $table->decimal('max_discount', 12, 2)->nullable();
            // Giá gói phải từ mức này trở lên mới áp dụng được mã.
            $table->decimal('min_order_amount', 12, 2)->nullable();

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->unsignedInteger('max_uses')->nullable();      // null = không giới hạn tổng lượt
            $table->unsignedInteger('max_uses_per_user')->default(1);

            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['is_active', 'ends_at']);
        });

        // Trống = áp dụng cho mọi gói. Có dòng = chỉ những gói được liệt kê.
        Schema::create('package_voucher', function (Blueprint $table) {
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();

            $table->primary(['voucher_id', 'package_id']);
        });

        Schema::create('voucher_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('voucher_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Mỗi đơn chỉ giữ một lượt; unique chặn luôn việc tạo hai lượt cho cùng một đơn.
            $table->foreignId('payment_id')->unique()->constrained()->cascadeOnDelete();
            $table->decimal('discount_amount', 12, 2);

            // Giữ chỗ lúc tạo đơn → trả tiền xong thì redeemed_at, đơn hỏng thì released_at.
            $table->dateTime('redeemed_at')->nullable();
            $table->dateTime('released_at')->nullable();
            $table->timestamps();

            $table->index(['voucher_id', 'released_at']);
            $table->index(['voucher_id', 'user_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            // restrictOnDelete: đã có đơn dùng mã thì không cho xoá mã, hoá đơn phải tra ngược được.
            $table->foreignId('voucher_id')->nullable()->after('package_id')->constrained()->restrictOnDelete();
            // `amount` vẫn là số tiền THỰC TRẢ sau giảm — IPN so khớp cột đó.
            $table->decimal('discount_amount', 12, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropForeign(['voucher_id']);
            $table->dropColumn(['voucher_id', 'discount_amount']);
        });

        Schema::dropIfExists('voucher_redemptions');
        Schema::dropIfExists('package_voucher');
        Schema::dropIfExists('vouchers');
    }
};
