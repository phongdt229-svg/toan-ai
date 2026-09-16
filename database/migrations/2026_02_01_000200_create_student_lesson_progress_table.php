<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §9 — theo dõi bài học đã học, section hoàn thành, thời gian học.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_lesson_progress', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            $table->enum('status', ['not_started', 'in_progress', 'completed'])->default('in_progress');
            // Mảng id của lesson_sections đã xem xong — tính progress_percent từ đây.
            $table->json('sections_completed')->nullable();
            $table->unsignedTinyInteger('progress_percent')->default(0);
            $table->unsignedInteger('time_spent_seconds')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'lesson_id']);
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_lesson_progress');
    }
};
