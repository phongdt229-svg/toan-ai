<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §10–12 — AI Tutor, cá nhân hóa, AI cho giáo viên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ai_conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('mode', ['chat', 'hint', 'explain', 'check_answer', 'similar_exercise', 'analyze_mistake']);
            // Ngữ cảnh giúp AI trả lời đúng bài đang học, và để giáo viên/admin truy vết.
            $table->enum('context_type', ['lesson', 'question', 'exam_attempt', 'free'])->default('free');
            $table->unsignedBigInteger('context_id')->nullable();
            $table->string('title', 191)->nullable();
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'last_message_at']);
        });

        Schema::create('ai_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ai_conversation_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['user', 'assistant']);
            $table->text('content');
            $table->string('model', 100)->nullable();
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->unsignedInteger('latency_ms')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('ai_usage', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('usage_date');
            $table->string('feature', 50);
            $table->unsignedInteger('request_count')->default(0);
            $table->unsignedInteger('failed_count')->default(0);
            $table->unsignedInteger('tokens_in')->default(0);
            $table->unsignedInteger('tokens_out')->default(0);
            $table->decimal('cost_estimate', 10, 6)->default(0);
            $table->timestamps();

            $table->unique(['user_id', 'usage_date', 'feature']);
            $table->index('usage_date');
        });

        // §12: AI chỉ tạo nháp — người duyệt từng mục mới thành nội dung thật.
        Schema::create('ai_generation_drafts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['questions', 'lesson']);
            $table->enum('status', ['pending', 'processing', 'ready', 'failed'])->default('pending');
            $table->json('input');
            // Mỗi mục có trạng thái riêng: pending / accepted / rejected.
            $table->json('output')->nullable();
            $table->string('error', 500)->nullable();
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        // §11: đề xuất học tập cá nhân hoá.
        Schema::create('recommendations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('type', ['review_lesson', 'practice_topic', 'take_exam']);
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->string('target_type', 50)->nullable();
            $table->unsignedBigInteger('target_id')->nullable();
            $table->string('difficulty', 10)->nullable();
            $table->string('reason', 255);
            $table->unsignedSmallInteger('priority')->default(0);
            $table->enum('status', ['new', 'seen', 'done'])->default('new');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'status', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recommendations');
        Schema::dropIfExists('ai_generation_drafts');
        Schema::dropIfExists('ai_usage');
        Schema::dropIfExists('ai_messages');
        Schema::dropIfExists('ai_conversations');
    }
};
