<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Lịch học theo tuần (D-01): mỗi ngày tối đa một khung giờ. Là nền cho điểm danh có/vắng/trễ và
 * cảnh báo phụ huynh — không có lịch thì không có khái niệm "vắng".
 *
 * Buổi học trong lộ trình (study_sessions) vẫn đi theo nhịp riêng; lịch chỉ nói KHI NÀO em ngồi vào học.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('study_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday'); // ISO: 1 = Thứ 2 … 7 = Chủ nhật
            $table->time('start_time');
            $table->unsignedSmallInteger('duration_minutes');
            // Ai đặt khung này — phụ huynh và học sinh cùng sửa được, cần biết để báo cho bên còn lại.
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['student_id', 'weekday']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('study_schedules');
    }
};
