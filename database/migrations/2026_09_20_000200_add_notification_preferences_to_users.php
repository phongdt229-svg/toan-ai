<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Mảng key các loại thông báo bị tắt (xem App\Support\NotificationType) — null/rỗng = bật hết,
            // opt-out chứ không opt-in để user cũ không tự nhiên mất hết thông báo sau khi có tính năng này.
            $table->json('notification_preferences')->nullable()->after('avatar');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('notification_preferences');
        });
    }
};
