<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Blog / Tin tức (PROJECT_PLAN.md, kế hoạch 26/09) — bài giới thiệu + khuyến mãi, phân theo danh mục. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('blog_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('slug', 100)->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('blog_posts', function (Blueprint $table) {
            $table->id();
            // restrictOnDelete: danh mục còn bài viết thì không xoá được — chặn ở tầng DB
            // ngoài cái chặn ở service, phòng khi có chỗ khác lỡ gọi thẳng Eloquent.
            $table->foreignId('blog_category_id')->constrained()->restrictOnDelete();
            $table->string('title', 191);
            $table->string('slug', 191)->unique();
            $table->string('excerpt', 300)->nullable();
            $table->longText('content');
            // Đường dẫn trên disk 'public', không lưu URL đầy đủ — đổi domain không phải sửa dữ liệu.
            $table->string('cover_path')->nullable();
            $table->enum('status', ['draft', 'published'])->default('draft');
            // Nullable nên timestamp() vẫn an toàn với strict mode — đúng tiền lệ Lesson.published_at.
            $table->timestamp('published_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'published_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('blog_posts');
        Schema::dropIfExists('blog_categories');
    }
};
