<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Đăng ký nhận push của từng trình duyệt.
 *
 * Một người có nhiều thiết bị → nhiều dòng. `endpoint` là định danh do trình duyệt cấp,
 * duy nhất toàn hệ thống: cùng một máy đăng ký lại thì ghi đè chứ không đẻ thêm dòng.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Endpoint của Google/Mozilla khá dài — text + hash index vì MariaDB giới hạn 191 ký tự cho unique.
            $table->string('endpoint', 500)->unique();
            $table->string('public_key', 191);
            $table->string('auth_token', 191);
            $table->string('user_agent', 191)->nullable();
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('push_subscriptions');
    }
};
