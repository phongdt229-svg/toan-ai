<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §17 — giao bài: Chọn lớp → Chọn học sinh → Chọn bài → Deadline → Cho phép làm lại → Giao bài.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->string('title', 191);
            $table->text('description')->nullable();
            // question_set: bộ câu hỏi riêng (bài tập về nhà) · exam: một đề có sẵn · lesson: học một bài.
            $table->enum('type', ['question_set', 'exam', 'lesson']);
            $table->foreignId('exam_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            // true: học sinh vào lớp sau cũng nhận bài này.
            $table->boolean('assign_to_all')->default(true);
            $table->dateTime('due_at')->nullable();
            $table->boolean('allow_retry')->default(false);
            $table->unsignedTinyInteger('max_attempts')->default(1);
            // closed: giáo viên khoá, không nhận nộp thêm.
            $table->enum('status', ['published', 'closed'])->default('published');
            $table->dateTime('published_at');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['class_id', 'status', 'due_at']);
        });

        Schema::create('assignment_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->decimal('points', 5, 2)->default(1);

            $table->unique(['assignment_id', 'question_id']);
        });

        // Một dòng / học sinh / bài giao. Là bảng tổng hợp để lọc nhanh "chưa làm", "điểm thấp"…
        Schema::create('assignment_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // assigned: chưa làm · submitted: đã nộp, còn tự luận chờ chấm · completed: xong, có điểm.
            $table->enum('status', ['assigned', 'submitted', 'completed'])->default('assigned');
            $table->decimal('score', 7, 2)->nullable();
            $table->decimal('max_score', 7, 2)->nullable();
            // % chuẩn hoá — so sánh được giữa bộ câu hỏi, đề thi có tổng điểm khác nhau.
            $table->unsignedTinyInteger('percent')->nullable();
            $table->unsignedTinyInteger('attempts_count')->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->boolean('is_late')->default(false);
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['assignment_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        // Lượt nộp của bài dạng question_set (đề và bài học đã có bảng riêng).
        Schema::create('assignment_submissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('assignment_id')->constrained()->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('attempt_no')->default(1);
            $table->json('answers')->nullable();
            $table->decimal('score', 7, 2)->default(0);
            $table->decimal('max_score', 7, 2)->default(0);
            $table->unsignedSmallInteger('correct_count')->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->boolean('is_late')->default(false);
            $table->dateTime('submitted_at');
            $table->timestamps();

            $table->index(['assignment_id', 'student_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('assignment_submissions');
        Schema::dropIfExists('assignment_students');
        Schema::dropIfExists('assignment_questions');
        Schema::dropIfExists('assignments');
    }
};
