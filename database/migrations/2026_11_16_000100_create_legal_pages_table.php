<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Nội dung trang chính sách (Điều khoản sử dụng, Chính sách bảo mật) admin soạn ở Quản trị → Chính sách.
 * Không có dòng = dùng nội dung mặc định viết trong code (public/legal/_*-default). Không lưu ai sửa:
 * audit log đã ghi, để bảng này không chứa dữ liệu cá nhân (khỏi phải thêm vào anonymise/export).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legal_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 50)->unique();
            $table->longText('content');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legal_pages');
    }
};
