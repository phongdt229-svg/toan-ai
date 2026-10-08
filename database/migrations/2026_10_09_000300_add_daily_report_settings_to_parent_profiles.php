<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TA-13: thông báo con bắt đầu / học xong buổi + báo cáo cuối ngày.
 * Mặc định TẮT cả hai — ngày nào cũng 2–3 thông báo là cách nhanh nhất để phụ huynh tắt hết thông báo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parent_profiles', function (Blueprint $table) {
            $table->boolean('session_events_enabled')->default(false)->after('weekly_report_enabled');
            $table->boolean('daily_report_enabled')->default(false)->after('session_events_enabled');
            $table->dateTime('last_daily_report_at')->nullable()->after('last_weekly_report_at');
        });
    }

    public function down(): void
    {
        Schema::table('parent_profiles', function (Blueprint $table) {
            $table->dropColumn(['session_events_enabled', 'daily_report_enabled', 'last_daily_report_at']);
        });
    }
};
