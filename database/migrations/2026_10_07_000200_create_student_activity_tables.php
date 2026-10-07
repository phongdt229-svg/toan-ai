<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Thời gian học thật (đặc tả "Logic" — active learning time).
 *
 * - `student_daily_activity`: cộng dồn theo ngày từ heartbeat 30 giây của trình duyệt. Không lưu từng heartbeat
 *   (120 dòng/giờ/học sinh) — chỉ cần tổng để báo "online X phút, học thực Y phút".
 * - `student_activity_logs`: sự kiện giao diện mà DB chưa có chỗ ghi (mở bài, xem phần, rời tab...).
 *   Trả lời câu hỏi / chat AI / học xong bài đã nằm ở question_attempts, ai_messages, student_lesson_progress.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_daily_activity', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('activity_date');
            $table->unsignedInteger('online_seconds')->default(0);
            $table->unsignedInteger('active_seconds')->default(0);
            $table->dateTime('first_seen_at')->nullable();
            $table->dateTime('last_seen_at')->nullable();
            $table->dateTime('last_active_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'activity_date']);
        });

        Schema::create('student_activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('event_type', 30);
            $table->string('path', 191)->nullable();
            $table->json('meta')->nullable();
            $table->dateTime('occurred_at');

            $table->index(['user_id', 'occurred_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_activity_logs');
        Schema::dropIfExists('student_daily_activity');
    }
};
