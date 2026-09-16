<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §7 — cây chương trình: Grade → Subject → Chapter → Topic → Lesson → Sections.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->string('name', 150);
            $table->string('slug', 150);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['grade_id', 'slug']);
            $table->index(['grade_id', 'is_active', 'sort_order']);
        });

        Schema::create('chapters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subject_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('slug', 191);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['subject_id', 'slug']);
            $table->index(['subject_id', 'is_active', 'sort_order']);
        });

        Schema::create('topics', function (Blueprint $table) {
            $table->id();
            $table->foreignId('chapter_id')->constrained()->cascadeOnDelete();
            $table->string('name', 191);
            $table->string('slug', 191);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['chapter_id', 'slug']);
            $table->index(['chapter_id', 'is_active', 'sort_order']);
        });

        Schema::create('lessons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('topic_id')->constrained()->cascadeOnDelete();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->string('summary', 500)->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->enum('difficulty', ['easy', 'medium', 'hard'])->default('medium');
            $table->unsignedSmallInteger('estimated_minutes')->default(15);
            // Quyết định ai xem được — đối chiếu với gói đang active (§18).
            $table->enum('access_level', ['free', 'pro', 'premium'])->default('free');
            $table->enum('status', ['draft', 'review', 'published'])->default('draft');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['topic_id', 'status', 'sort_order']);
        });

        Schema::create('lesson_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('lesson_id')->constrained()->cascadeOnDelete();
            // 9 khối của một lesson theo §8.
            $table->enum('type', [
                'theory', 'example', 'insight', 'formula', 'common_mistake',
                'quiz', 'practice', 'advanced', 'test',
            ]);
            $table->string('title', 191)->nullable();
            $table->longText('content')->nullable();  // HTML + KaTeX
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();

            $table->index(['lesson_id', 'sort_order']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lesson_sections');
        Schema::dropIfExists('lessons');
        Schema::dropIfExists('topics');
        Schema::dropIfExists('chapters');
        Schema::dropIfExists('subjects');
    }
};
