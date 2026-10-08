<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Điểm danh theo lịch học (TA-09/TA-10): mỗi khung giờ trong lịch tuần sinh một dòng khi tới giờ.
 *
 * Giờ bắt đầu/thời lượng CHỤP lại lúc tạo dòng — đổi lịch giữa chừng không làm đổi buổi đang chấm.
 * `baseline_active_seconds`: tổng giây học thực của học sinh lúc khung giờ bắt đầu; học thực trong khung
 * = tổng lúc chốt − baseline (thời gian học thật chỉ lưu theo ngày, không theo từng phút).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')->constrained('users')->cascadeOnDelete();
            $table->date('attendance_date');
            $table->dateTime('scheduled_start');
            $table->dateTime('scheduled_end');
            $table->unsignedSmallInteger('scheduled_minutes');
            $table->enum('status', ['pending', 'late', 'absent_pending', 'in_progress', 'present', 'partial', 'absent'])
                ->default('pending');
            $table->unsignedInteger('baseline_active_seconds')->default(0);
            $table->unsignedInteger('active_seconds')->default(0);
            $table->dateTime('entered_at')->nullable();
            $table->unsignedSmallInteger('late_minutes')->nullable();
            $table->boolean('quiz_submitted')->default(false);
            $table->dateTime('finalized_at')->nullable();
            // Đã báo phụ huynh "chưa vào học" / "vắng" — mỗi loại một lần cho mỗi buổi.
            $table->dateTime('not_started_notified_at')->nullable();
            $table->dateTime('absent_notified_at')->nullable();
            $table->timestamps();

            $table->unique(['student_id', 'attendance_date']);
            $table->index(['status', 'scheduled_end']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_attendances');
    }
};
