<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Hỏi đáp cho học sinh.
 *
 * `answers_count` và `reports_count` là cột đếm sẵn có chủ đích: danh sách câu hỏi hiển thị
 * hai số này trên mọi dòng, đếm lại bằng query con mỗi lần tải là tốn vô ích.
 * Nguồn sự thật vẫn là hai bảng con — service cập nhật lại sau mỗi thao tác.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qa_questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            // Gắn vào chủ đề của khung chương trình → tự xếp theo lớp/chương, không cần bộ lọc tay.
            $table->foreignId('topic_id')->constrained()->restrictOnDelete();
            $table->string('title', 191);
            $table->text('body');
            $table->enum('status', ['open', 'resolved', 'hidden'])->default('open');
            $table->unsignedBigInteger('best_answer_id')->nullable();
            $table->unsignedInteger('answers_count')->default(0);
            $table->unsignedInteger('reports_count')->default(0);
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['topic_id', 'status']);
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('qa_answers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained('qa_questions')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->enum('status', ['visible', 'hidden'])->default('visible');
            $table->unsignedInteger('reports_count')->default(0);
            $table->timestamps();

            $table->index(['question_id', 'status']);
        });

        Schema::create('qa_reports', function (Blueprint $table) {
            $table->id();
            $table->morphs('reportable');
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('reason', 191)->nullable();
            $table->timestamps();

            // Một người chỉ báo được một lần cho mỗi nội dung — nếu không thì một em
            // bấm mười lần là ẩn được bài của bạn khác.
            $table->unique(['reportable_type', 'reportable_id', 'user_id'], 'qa_reports_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qa_reports');
        Schema::dropIfExists('qa_answers');
        Schema::dropIfExists('qa_questions');
    }
};
