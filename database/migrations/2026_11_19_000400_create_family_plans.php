<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Gói gia đình: một đơn, nhiều học sinh. Người mua (chủ gói) nhận MÃ GIA ĐÌNH, anh chị em tự nhập mã
 * để vào gói (chạy được cả khi cổng phụ huynh đang ẩn — chốt 19/11).
 *
 * Quyền của thành viên KHÔNG có bản ghi subscription riêng: SubscriptionService::effective() tìm thêm
 * gói mà em là thành viên. Gói chính hết hạn / bị hoàn tiền là mọi thành viên mất quyền cùng lúc.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            // Tổng số học sinh dùng chung một đơn, KỂ CẢ chủ gói. 1 = gói thường.
            $table->unsignedTinyInteger('max_members')->default(1)->after('duration_days');
        });

        Schema::table('subscriptions', function (Blueprint $table) {
            $table->string('join_code', 12)->nullable()->unique()->after('source');
        });

        Schema::create('subscription_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subscription_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->dateTime('joined_at');
            $table->timestamps();

            $table->unique(['subscription_id', 'user_id']);
            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_members');
        Schema::table('subscriptions', fn (Blueprint $table) => $table->dropColumn('join_code'));
        Schema::table('packages', fn (Blueprint $table) => $table->dropColumn('max_members'));
    }
};
