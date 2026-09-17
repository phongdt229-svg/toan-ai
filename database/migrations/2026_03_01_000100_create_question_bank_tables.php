<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §15 — ngân hàng câu hỏi 6 loại.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('questions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->foreignId('topic_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('lesson_id')->nullable()->constrained()->nullOnDelete();
            $table->enum('type', [
                'multiple_choice', 'single_choice', 'true_false',
                'fill_blank', 'short_answer', 'essay',
            ]);
            $table->text('content');
            $table->text('explanation')->nullable();
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            // Đáp án cho loại không dùng option (fill_blank / short_answer / true_false).
            $table->json('correct_answer')->nullable();
            $table->decimal('points', 5, 2)->default(1);
            $table->enum('status', ['draft', 'published'])->default('draft');
            // AI sinh ra hay người soạn — phục vụ rà soát chất lượng (§12).
            $table->enum('source', ['manual', 'ai'])->default('manual');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['grade_id', 'topic_id', 'difficulty', 'status']);
            $table->index(['status', 'type']);
        });

        Schema::create('question_options', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->text('content');
            $table->boolean('is_correct')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['question_id', 'sort_order']);
        });

        Schema::create('tags', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->timestamps();
        });

        Schema::create('question_tags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_id')->constrained()->cascadeOnDelete();
            $table->foreignId('tag_id')->constrained()->cascadeOnDelete();

            $table->unique(['question_id', 'tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('question_tags');
        Schema::dropIfExists('tags');
        Schema::dropIfExists('question_options');
        Schema::dropIfExists('questions');
    }
};
