<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §13 — lớp học của giáo viên.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('classes', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150);
            // Mã để học sinh tự tham gia lớp — ngắn, không phân biệt hoa/thường.
            $table->string('code', 8)->unique();
            $table->foreignId('grade_id')->constrained()->cascadeOnDelete();
            $table->string('description', 500)->nullable();
            $table->foreignId('owner_teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('status', ['active', 'archived'])->default('active');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['owner_teacher_id', 'status']);
        });

        // Một lớp có thể có giáo viên phụ; quyền truy cập lớp đi qua bảng này, không qua owner_teacher_id.
        Schema::create('teacher_classes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->enum('role', ['owner', 'assistant'])->default('owner');
            $table->timestamps();

            $table->unique(['class_id', 'teacher_id']);
        });

        Schema::create('class_students', function (Blueprint $table) {
            $table->id();
            $table->foreignId('class_id')->constrained('classes')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            // removed: giữ lại dòng để lịch sử bài tập/điểm không mất khi học sinh rời lớp.
            $table->enum('status', ['active', 'removed'])->default('active');
            $table->timestamp('joined_at')->nullable();
            $table->timestamps();

            $table->unique(['class_id', 'student_id']);
            $table->index(['student_id', 'status']);
        });

        Schema::create('teacher_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('class_id')->nullable()->constrained('classes')->nullOnDelete();
            $table->text('content');
            // Phụ huynh chỉ thấy nhận xét được đánh dấu (§14).
            $table->boolean('visible_to_parent')->default(true);
            $table->timestamps();

            $table->index(['student_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('teacher_comments');
        Schema::dropIfExists('class_students');
        Schema::dropIfExists('teacher_classes');
        Schema::dropIfExists('classes');
    }
};
