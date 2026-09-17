<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §16 — đề kiểm tra: Start exam → Answer → Submit → Calculate result → Review.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->text('description')->nullable();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['practice', 'quiz', 'test'])->default('test');
            $table->unsignedSmallInteger('duration_minutes')->default(45);
            // Hai cột dưới là số tổng hợp, tính lại mỗi khi đổi câu hỏi (ExamBuilderService).
            $table->unsignedSmallInteger('total_questions')->default(0);
            $table->decimal('total_points', 7, 2)->default(0);
            $table->enum('difficulty', ['easy', 'medium', 'hard', 'mixed'])->default('mixed');
            $table->enum('access_level', ['free', 'pro', 'premium'])->default('free');
            $table->unsignedTinyInteger('max_attempts')->default(1);
            $table->boolean('shuffle_questions')->default(true);
            $table->boolean('shuffle_options')->default(true);
            // Lộ đáp án ngay cho đề đang mở sẽ làm lộ đề cho người làm sau.
            $table->boolean('show_answers_after_submit')->default(true);
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_to')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['grade_id', 'status']);
        });

        Schema::create('exam_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            // Điểm trong đề có thể khác điểm mặc định của câu hỏi trong ngân hàng.
            $table->decimal('points', 5, 2)->default(1);
            $table->timestamps();

            $table->unique(['exam_id', 'question_id']);
        });

        Schema::create('exam_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            // dateTime thay vì timestamp: MariaDB 10.4 (explicit_defaults_for_timestamp=OFF)
            // từ chối cột TIMESTAMP NOT NULL thứ hai không có default.
            $table->dateTime('started_at');
            // Nguồn sự thật về thời gian — client chỉ hiển thị đếm ngược.
            $table->dateTime('expires_at');
            $table->timestamp('submitted_at')->nullable();
            // submitted = đã nộp nhưng còn câu tự luận chờ chấm; graded = điểm đã đầy đủ.
            $table->enum('status', ['in_progress', 'submitted', 'graded'])->default('in_progress');
            $table->boolean('auto_submitted')->default(false);
            $table->decimal('score', 7, 2)->nullable();
            $table->decimal('total_points', 7, 2)->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            // Thứ tự câu hỏi / lựa chọn đã xáo cho riêng lượt này — tải lại trang không bị đổi.
            $table->json('question_order');
            $table->json('option_order')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'exam_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::create('student_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_attempt_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->json('answer')->nullable();
            // null = chưa chấm (lúc đang làm, hoặc câu tự luận chờ giáo viên).
            $table->boolean('is_correct')->nullable();
            $table->decimal('score', 5, 2)->nullable();
            $table->decimal('max_score', 5, 2)->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->foreignId('graded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('feedback')->nullable();
            $table->timestamp('graded_at')->nullable();
            $table->timestamps();

            $table->unique(['exam_attempt_id', 'question_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_answers');
        Schema::dropIfExists('exam_attempts');
        Schema::dropIfExists('exam_questions');
        Schema::dropIfExists('exams');
    }
};
