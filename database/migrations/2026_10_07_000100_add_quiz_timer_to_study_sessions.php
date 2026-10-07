<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kiểm tra cuối buổi 15 phút (đặc tả module 5) — giờ do server quyết như đề kiểm tra.
 * dateTime chứ không timestamp: bảng đã có cột thời gian NOT NULL (MariaDB 10.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dateTime('quiz_started_at')->nullable()->after('quiz_question_ids');
            $table->dateTime('quiz_expires_at')->nullable()->after('quiz_started_at');
            $table->boolean('quiz_auto_submitted')->default(false)->after('quiz_submitted_at');
        });
    }

    public function down(): void
    {
        Schema::table('study_sessions', function (Blueprint $table) {
            $table->dropColumn(['quiz_started_at', 'quiz_expires_at', 'quiz_auto_submitted']);
        });
    }
};
