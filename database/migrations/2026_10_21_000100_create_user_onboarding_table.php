<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tiến độ hướng dẫn người mới (OB-03): tour đã xem / bỏ qua, trang đã ghé. Lưu ở server để đổi máy không bị
 * mời lại tour đã xem. Danh sách "Bắt đầu" KHÔNG lưu ở đây — nó tính từ dữ liệu thật (đã làm đầu vào chưa…).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_onboarding', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('key', 80); // "tour:student.dashboard", "visit:parent.children.show", "checklist:hidden"
            $table->enum('status', ['completed', 'dismissed', 'visited', 'hidden']);
            $table->unsignedTinyInteger('step')->nullable(); // bỏ ngang ở bước mấy — để biết bước nào khó hiểu
            $table->timestamps();

            $table->unique(['user_id', 'key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_onboarding');
    }
};
