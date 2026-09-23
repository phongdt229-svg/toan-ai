<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đổi email: địa chỉ mới nằm chờ ở đây cho tới khi người dùng bấm link gửi TỚI ĐỊA CHỈ MỚI.
 *
 * Không đổi thẳng `users.email`: gõ nhầm một ký tự là mất luôn đường đăng nhập và đặt lại mật khẩu.
 * Cột này KHÔNG unique — hai người cùng đang chờ đổi sang một địa chỉ là chuyện bình thường,
 * người bấm link trước được nhận, người sau bị từ chối lúc xác nhận.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('pending_email')->nullable()->after('email');
            $table->dateTime('pending_email_at')->nullable()->after('pending_email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['pending_email', 'pending_email_at']);
        });
    }
};
