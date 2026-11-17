<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * legal_pages thành CMS trang chính sách: admin tạo / sửa trang, slug cố định từ lúc tạo (link đã đăng ký
 * với Bộ Công Thương, đã chia sẻ không được gãy). Hai trang hệ thống (Điều khoản, Bảo mật) không xoá / tắt được;
 * nội dung NULL = dùng bản mặc định trong code. Nạp sẵn 6 trang đang chạy trên production (database/data/legal_pages.php).
 */
return new class extends Migration
{
    private const SYSTEM = [
        'terms' => ['slug' => 'dieu-khoan-su-dung', 'title' => 'Điều khoản sử dụng', 'sort_order' => 1,
            'meta_description' => 'Điều khoản sử dụng dịch vụ học Toán trực tuyến Math AI: tài khoản, gói học, thanh toán, quyền và nghĩa vụ.'],
        'privacy' => ['slug' => 'chinh-sach-bao-mat', 'title' => 'Chính sách bảo mật', 'sort_order' => 2,
            'meta_description' => 'Math AI thu thập, sử dụng và bảo vệ dữ liệu của học sinh, phụ huynh, giáo viên như thế nào.'],
    ];

    public function up(): void
    {
        Schema::table('legal_pages', function (Blueprint $table) {
            $table->string('title', 191)->default('')->after('slug');
            $table->string('meta_description', 300)->nullable()->after('title');
            $table->boolean('is_system')->default(false)->after('meta_description');
            $table->boolean('is_active')->default(true)->after('is_system');
            $table->unsignedSmallInteger('sort_order')->default(0)->after('is_active');
            $table->longText('content')->nullable()->change();
        });

        foreach (self::SYSTEM as $oldSlug => $page) {
            // Bản admin đã soạn (slug cũ 'terms'/'privacy') giữ nguyên nội dung, chỉ đổi slug sang đường dẫn thật.
            DB::table('legal_pages')->where('slug', $oldSlug)->update(['slug' => $page['slug']]);

            if (! DB::table('legal_pages')->where('slug', $page['slug'])->exists()) {
                DB::table('legal_pages')->insert($page + ['content' => null, 'created_at' => now(), 'updated_at' => now()]);
            }

            DB::table('legal_pages')->where('slug', $page['slug'])
                ->update(['title' => $page['title'], 'meta_description' => $page['meta_description'], 'is_system' => true, 'is_active' => true, 'sort_order' => $page['sort_order']]);
        }

        // Ngày thật của bản đang chạy trên production — không phải ngày chạy migration.
        $since = '2026-10-29 00:00:00';

        foreach (require database_path('data/legal_pages.php') as $slug => $page) {
            if (! DB::table('legal_pages')->where('slug', $slug)->exists()) {
                DB::table('legal_pages')->insert($page + ['slug' => $slug, 'is_active' => true, 'created_at' => $since, 'updated_at' => $since]);
            }
        }
    }

    public function down(): void
    {
        DB::table('legal_pages')->whereIn('slug', array_keys(require database_path('data/legal_pages.php')))->delete();
        DB::table('legal_pages')->whereNull('content')->delete();
        DB::table('legal_pages')->where('slug', 'dieu-khoan-su-dung')->update(['slug' => 'terms']);
        DB::table('legal_pages')->where('slug', 'chinh-sach-bao-mat')->update(['slug' => 'privacy']);

        Schema::table('legal_pages', function (Blueprint $table) {
            $table->dropColumn(['title', 'meta_description', 'is_system', 'is_active', 'sort_order']);
        });
    }
};
