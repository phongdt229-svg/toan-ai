<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Gói Free/Pro/Premium (§18). Giá ở đây chỉ là giá khởi tạo — admin sửa trong trang quản trị,
 * code không bao giờ đọc giá từ chỗ nào khác ngoài bảng `packages`.
 * Chạy lại an toàn: không ghi đè giá admin đã sửa.
 *
 * Bảng giá 08/10/2026: Free học thử 3 buổi lộ trình · Pro 12 tháng 699.000₫ · Premium 12 tháng 1.200.000₫.
 * DB đang chạy được đổi sang bảng giá này bằng migration 2026_10_08_000300 (seeder không sửa gói đã có).
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->packages() as $data) {
            $features = $data['features'];
            unset($data['features']);

            $package = Package::firstOrCreate(['slug' => $data['slug']], $data + ['currency' => 'VND', 'is_active' => true]);

            if (! $package->wasRecentlyCreated) {
                continue;
            }

            foreach ($features as $i => [$key, $label, $value, $limit]) {
                $package->features()->create([
                    'key' => $key,
                    'label' => $label,
                    'value' => $value,
                    'limit_value' => $limit,
                    'show_on_pricing' => true,
                    'sort_order' => $i + 1,
                ]);
            }
        }
    }

    /** @return array<int, array<string, mixed>> mỗi gói kèm `features` = [key, nhãn, value, limit_value] */
    protected function packages(): array
    {
        return [
            [
                'slug' => 'free', 'name' => 'Free', 'tier' => 'free', 'price' => 0, 'duration_days' => null,
                'description' => 'Làm kiểm tra đầu vào và học thử 3 buổi theo lộ trình riêng — không cần thẻ.',
                'is_default' => true, 'sort_order' => 1,
                'features' => [
                    ['path.sessions', 'Học thử 3 buổi theo lộ trình cá nhân', '1', 3],
                    ['display.lessons', 'Bài học cơ bản của mọi lớp', '1', null],
                    ['practice.daily_questions', '30 câu luyện tập mỗi ngày', '1', 30],
                    ['ai.daily_requests', '10 lượt hỏi AI mỗi ngày', '1', 10],
                    ['ai.image_daily', 'Chụp ảnh đề: 3 lần mỗi ngày', '1', 3],
                    ['ai.advanced_modes', 'AI phân tích lỗi sai, bài tương tự', '0', null],
                    ['reports.advanced', 'Báo cáo nâng cao cho phụ huynh', '0', null],
                ],
            ],
            [
                'slug' => 'pro-nam', 'name' => 'Pro 12 tháng', 'tier' => 'pro', 'price' => 699000, 'duration_days' => 365,
                'description' => 'Học trọn năm theo lộ trình riêng, toàn bộ bài học, luyện tập không giới hạn, AI Tutor mỗi ngày.',
                'is_highlighted' => true, 'sort_order' => 2,
                'features' => [
                    ['path.sessions', 'Lộ trình cá nhân không giới hạn buổi', '1', null],
                    ['display.lessons', 'Toàn bộ bài học Pro', '1', null],
                    ['practice.daily_questions', 'Luyện tập không giới hạn', '1', null],
                    ['ai.daily_requests', '50 lượt hỏi AI mỗi ngày', '1', 50],
                    ['ai.image_daily', 'Chụp ảnh đề: 20 lần mỗi ngày', '1', 20],
                    ['ai.advanced_modes', 'AI phân tích lỗi sai, bài tương tự', '0', null],
                    ['reports.advanced', 'Báo cáo nâng cao cho phụ huynh', '0', null],
                ],
            ],
            [
                'slug' => 'premium-nam', 'name' => 'Premium 12 tháng', 'tier' => 'premium', 'price' => 1200000, 'duration_days' => 365,
                'description' => 'Tất cả của Pro + AI nâng cao + báo cáo chi tiết cho phụ huynh, dùng trọn năm.', 'sort_order' => 3,
                'features' => [
                    ['path.sessions', 'Lộ trình cá nhân không giới hạn buổi', '1', null],
                    ['display.lessons', 'Toàn bộ bài học Pro & Premium', '1', null],
                    ['practice.daily_questions', 'Luyện tập không giới hạn', '1', null],
                    ['ai.daily_requests', '200 lượt hỏi AI mỗi ngày', '1', 200],
                    ['ai.image_daily', 'Chụp ảnh đề: 50 lần mỗi ngày', '1', 50],
                    ['ai.advanced_modes', 'AI phân tích lỗi sai, bài tương tự', '1', null],
                    ['reports.advanced', 'Báo cáo nâng cao cho phụ huynh', '1', null],
                ],
            ],
        ];
    }
}
