<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bảng giá 08/10/2026: Free học thử 3 buổi lộ trình · Pro 12 tháng 699.000₫ · Premium 12 tháng 1.200.000₫.
 *
 * PackageSeeder không sửa gói đã tồn tại, nên DB đang chạy đổi tại đây:
 * - gói tháng NGỪNG BÁN (is_active = false), không xoá — đã có đơn/đăng ký trỏ tới, và người đang dùng vẫn dùng tới hết hạn;
 * - thêm quyền lợi `path.sessions` cho mọi gói: Free = 3 buổi, gói trả phí = không giới hạn.
 * Gói nào không tìm thấy theo slug (DB đã đổi tay) thì bỏ qua, không tự tạo.
 */
return new class extends Migration
{
    private const CHANGES = [
        'free' => ['description' => 'Làm kiểm tra đầu vào và học thử 3 buổi theo lộ trình riêng — không cần thẻ.'],
        'pro-nam' => [
            'price' => 699000, 'is_highlighted' => true, 'sort_order' => 2,
            'description' => 'Học trọn năm theo lộ trình riêng, toàn bộ bài học, luyện tập không giới hạn, AI Tutor mỗi ngày.',
        ],
        'premium-nam' => [
            'price' => 1200000, 'is_highlighted' => false, 'sort_order' => 3,
            'description' => 'Tất cả của Pro + AI nâng cao + báo cáo chi tiết cho phụ huynh, dùng trọn năm.',
        ],
        'pro-thang' => ['is_active' => false, 'is_highlighted' => false],
        'premium-thang' => ['is_active' => false, 'is_highlighted' => false],
    ];

    public function up(): void
    {
        foreach (self::CHANGES as $slug => $values) {
            DB::table('packages')->where('slug', $slug)->update($values + ['updated_at' => now()]);
        }

        foreach (DB::table('packages')->get(['id', 'tier']) as $package) {
            if (DB::table('package_features')->where('package_id', $package->id)->where('key', 'path.sessions')->exists()) {
                continue;
            }

            $free = $package->tier === 'free';

            // Đứng đầu danh sách quyền lợi: với gói Free đây là điều quan trọng nhất cần nói rõ.
            DB::table('package_features')->where('package_id', $package->id)->increment('sort_order');
            DB::table('package_features')->insert([
                'package_id' => $package->id,
                'key' => 'path.sessions',
                'label' => $free ? 'Học thử 3 buổi theo lộ trình cá nhân' : 'Lộ trình cá nhân không giới hạn buổi',
                'value' => '1',
                'limit_value' => $free ? 3 : null,
                'show_on_pricing' => true,
                'sort_order' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('package_features')->where('key', 'path.sessions')->delete();
        DB::table('packages')->where('slug', 'pro-nam')->update(['price' => 990000, 'is_highlighted' => false, 'sort_order' => 3]);
        DB::table('packages')->where('slug', 'premium-nam')->update(['price' => 1990000, 'sort_order' => 5]);
        DB::table('packages')->whereIn('slug', ['pro-thang', 'premium-thang'])->update(['is_active' => true]);
        DB::table('packages')->where('slug', 'pro-thang')->update(['is_highlighted' => true]);
    }
};
