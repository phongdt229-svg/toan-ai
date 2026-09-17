<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Index cho các truy vấn chạy thường xuyên phát hiện ở Phase 10:
 * - đếm câu luyện tập hôm nay (giới hạn gói Free) mỗi lần bắt đầu luyện
 * - thống kê người dùng mới / học sinh hoạt động / doanh thu trên dashboard admin
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('question_attempts', function (Blueprint $table) {
            $table->index(['user_id', 'context', 'created_at'], 'qa_user_context_created_idx');
            $table->index('created_at', 'qa_created_idx');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('created_at', 'users_created_idx');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->index(['status', 'paid_at'], 'payments_status_paid_idx');
        });

        Schema::table('payment_webhook_logs', function (Blueprint $table) {
            $table->index(['result', 'created_at'], 'pwl_result_created_idx');
        });

        Schema::table('student_lesson_progress', function (Blueprint $table) {
            $table->index(['status', 'completed_at'], 'slp_status_completed_idx');
        });
    }

    public function down(): void
    {
        Schema::table('question_attempts', function (Blueprint $table) {
            $table->dropIndex('qa_user_context_created_idx');
            $table->dropIndex('qa_created_idx');
        });
        Schema::table('users', fn (Blueprint $table) => $table->dropIndex('users_created_idx'));
        Schema::table('payments', fn (Blueprint $table) => $table->dropIndex('payments_status_paid_idx'));
        Schema::table('payment_webhook_logs', fn (Blueprint $table) => $table->dropIndex('pwl_result_created_idx'));
        Schema::table('student_lesson_progress', fn (Blueprint $table) => $table->dropIndex('slp_status_completed_idx'));
    }
};
