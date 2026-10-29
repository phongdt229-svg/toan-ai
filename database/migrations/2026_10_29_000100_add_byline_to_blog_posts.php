<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/*
 * Người biên soạn CÔNG KHAI của bài viết (GEO / E-E-A-T): tên + chức danh admin tự gõ, vd "Cô Nguyễn Thị A"
 * — "Giáo viên Toán THCS". Tách khỏi created_by vì đó là tài khoản admin nội bộ, không phải người viết thật.
 * Để trống thì bài vẫn khai tác giả là tổ chức như trước.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->string('byline_name', 120)->nullable()->after('excerpt');
            $table->string('byline_title', 160)->nullable()->after('byline_name');
        });
    }

    public function down(): void
    {
        Schema::table('blog_posts', function (Blueprint $table) {
            $table->dropColumn(['byline_name', 'byline_title']);
        });
    }
};
