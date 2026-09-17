<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §11 — AI cá nhân hóa cần theo dõi: chủ đề, câu hỏi, độ khó, đúng/sai, thời gian, số lần làm.
 *
 * `student_topic_mastery` là bảng tổng hợp (đọc nhanh cho dashboard).
 * `question_attempts` giữ chi tiết từng lần trả lời — không có bảng này thì
 * Phase 7 không có dữ liệu để phân tích lỗi và đề xuất lộ trình.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('question_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            // Cùng một câu hỏi có thể xuất hiện ở luyện tập, đề thi hoặc bài giao.
            $table->enum('context', ['practice', 'exam', 'assignment'])->default('practice');
            $table->unsignedBigInteger('context_id')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->json('answer')->nullable();
            // null = chờ người chấm (câu tự luận).
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 5, 2)->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->unsignedSmallInteger('attempt_no')->default(1);
            $table->timestamps();

            $table->index(['user_id', 'question_id']);
            $table->index(['user_id', 'topic_id', 'created_at']);
        });

        Schema::create('student_topic_mastery', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('correct_count')->default(0);
            $table->unsignedInteger('wrong_count')->default(0);
            $table->unsignedInteger('avg_time_seconds')->default(0);
            // 0–100. Tính có trọng số theo độ khó, xem MasteryService.
            $table->unsignedTinyInteger('mastery_score')->default(0);
            $table->timestamp('last_practiced_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'topic_id']);
            $table->index(['user_id', 'mastery_score']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_topic_mastery');
        Schema::dropIfExists('question_attempts');
    }
};
