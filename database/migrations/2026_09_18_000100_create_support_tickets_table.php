<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Yêu cầu hỗ trợ và báo lỗi nội dung gửi từ trang công khai hoặc trong app.
 * Khách chưa đăng nhập cũng gửi được nên name/email lưu thẳng ở đây, không bắt buộc có user_id.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('support_tickets', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique(); // mã ngắn để người gửi tra cứu khi liên hệ lại
            $table->enum('type', ['support', 'content_error', 'payment', 'other'])->default('support');
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name', 100);
            $table->string('email');
            $table->string('subject', 191);
            $table->text('message');

            // Ngữ cảnh: trang đang xem và (nếu báo lỗi nội dung) bài học / câu hỏi cụ thể.
            $table->string('context_url', 500)->nullable();
            $table->string('related_type', 100)->nullable();
            $table->unsignedBigInteger('related_id')->nullable();

            $table->enum('status', ['new', 'in_progress', 'resolved', 'closed'])->default('new');
            $table->text('admin_note')->nullable();
            $table->foreignId('handled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('handled_at')->nullable();

            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 191)->nullable();
            $table->timestamps();

            $table->index(['status', 'created_at']);
            $table->index(['type', 'status']);
            $table->index(['related_type', 'related_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('support_tickets');
    }
};
