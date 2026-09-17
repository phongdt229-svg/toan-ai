<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §35 — Giáo trình cá nhân hóa (lộ trình 4 giai đoạn), §36 buổi học, §37 kiểm tra cuối buổi.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('learning_paths', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('placement_test_id')->nullable()->constrained()->nullOnDelete();
            // archived: bị thay bằng lộ trình mới khi học sinh làm lại kiểm tra đầu vào.
            $table->enum('status', ['active', 'completed', 'archived'])->default('active');
            $table->unsignedTinyInteger('items_per_session')->default(3);
            $table->unsignedSmallInteger('total_sessions')->default(0);
            $table->unsignedSmallInteger('completed_sessions')->default(0);
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->dateTime('generated_at');
            $table->timestamps();

            $table->index(['user_id', 'status']);
        });

        Schema::create('learning_path_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->enum('stage', ['foundation', 'consolidation', 'advanced', 'exam_practice']);
            $table->string('name', 100);
            $table->unsignedTinyInteger('sort_order');
            $table->enum('status', ['locked', 'in_progress', 'done'])->default('locked');
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->timestamps();
        });

        Schema::create('study_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('learning_path_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('session_no');
            // quiz_pending: học xong các mục, còn kiểm tra cuối buổi (§37).
            $table->enum('status', ['planned', 'quiz_pending', 'done'])->default('planned');
            $table->json('quiz_question_ids')->nullable();
            $table->unsignedTinyInteger('quiz_percent')->nullable();
            $table->dateTime('quiz_submitted_at')->nullable();
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['learning_path_id', 'session_no']);
        });

        Schema::create('learning_path_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('learning_path_stage_id')->constrained()->cascadeOnDelete();
            $table->foreignId('study_session_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('item_type', ['lesson', 'practice', 'exam']);
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            // lesson_id hoặc exam_id tuỳ item_type.
            $table->unsignedBigInteger('target_id')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->nullable();
            // review: mục ôn tập hệ thống tự chèn khi học sinh tụt điểm (tự điều chỉnh §35).
            $table->enum('origin', ['plan', 'review'])->default('plan');
            $table->string('title', 191);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('status', ['pending', 'done'])->default('pending');
            $table->dateTime('completed_at')->nullable();
            $table->timestamps();

            $table->index(['learning_path_stage_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('learning_path_items');
        Schema::dropIfExists('study_sessions');
        Schema::dropIfExists('learning_path_stages');
        Schema::dropIfExists('learning_paths');
    }
};
