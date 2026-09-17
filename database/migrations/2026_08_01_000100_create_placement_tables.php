<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * §34 — Kiểm tra đầu vào.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placement_tests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['in_progress', 'graded'])->default('in_progress');
            // dateTime: MariaDB 10.4 không nhận cột TIMESTAMP NOT NULL thứ hai.
            $table->dateTime('started_at');
            $table->dateTime('expires_at');
            $table->dateTime('submitted_at')->nullable();
            $table->boolean('auto_submitted')->default(false);
            $table->unsignedTinyInteger('total_questions')->default(0);
            $table->unsignedTinyInteger('correct_count')->default(0);
            // Thang 10 — §34 phân loại theo điểm: ≤5 Trung bình · ≤8 Khá · >8 Giỏi.
            $table->decimal('score', 4, 2)->nullable();
            $table->enum('level_result', ['average', 'good', 'excellent'])->nullable();
            // Mức độ hiểu: % đúng có trọng số độ khó (đúng câu khó đáng giá hơn).
            $table->unsignedTinyInteger('understanding_percent')->nullable();
            $table->unsignedSmallInteger('avg_seconds_per_question')->nullable();
            $table->json('weak_topics')->nullable();
            $table->text('analysis')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        // Chụp lại nội dung câu hỏi lúc ra đề — giáo viên sửa ngân hàng sau đó không làm đổi bài đã làm.
        Schema::create('placement_test_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_test_id')->constrained()->cascadeOnDelete();
            // null = câu AI sinh bổ sung khi ngân hàng không đủ.
            $table->foreignId('question_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 30);
            $table->enum('difficulty', ['easy', 'medium', 'hard']);
            $table->text('content');
            $table->json('options')->nullable();
            $table->json('correct_answer')->nullable();
            $table->text('explanation')->nullable();
            $table->decimal('points', 5, 2)->default(1);
            $table->unsignedTinyInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('placement_test_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('placement_test_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_test_question_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();
            $table->boolean('is_correct')->default(false);
            $table->decimal('score', 5, 2)->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->timestamps();

            $table->unique(['placement_test_id', 'placement_test_question_id'], 'pta_test_question_unique');
        });

        // Kết quả đầu vào và kiểm tra cuối buổi cũng nuôi mastery / AI như luyện tập.
        DB::statement("ALTER TABLE question_attempts MODIFY context ENUM('practice','exam','assignment','placement','session_quiz') NOT NULL DEFAULT 'practice'");
    }

    public function down(): void
    {
        DB::statement("DELETE FROM question_attempts WHERE context IN ('placement','session_quiz')");
        DB::statement("ALTER TABLE question_attempts MODIFY context ENUM('practice','exam','assignment') NOT NULL DEFAULT 'practice'");

        Schema::dropIfExists('placement_test_answers');
        Schema::dropIfExists('placement_test_questions');
        Schema::dropIfExists('placement_tests');
    }
};
