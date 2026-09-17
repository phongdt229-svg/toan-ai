<?php

namespace Database\Seeders;

use App\Models\Package;
use Illuminate\Database\Seeder;

/**
 * Gói Free/Pro/Premium (§18). Giá ở đây chỉ là giá khởi tạo — admin sửa trong trang quản trị,
 * code không bao giờ đọc giá từ chỗ nào khác ngoài bảng `packages`.
 * Chạy lại an toàn: không ghi đè giá admin đã sửa.
 */
class PackageSeeder extends Seeder
{
    public function run(): void
    {
        $packages = [
            [
                'slug' => 'free', 'name' => 'Free', 'tier' => 'free', 'price' => 0, 'duration_days' => null,
                'description' => 'Bắt đầu học miễn phí, không cần thẻ.', 'is_default' => true, 'sort_order' => 1,
                'features' => [
                    ['display.lessons', 'Bài học cơ bản của mọi lớp', '1', null],
                    ['practice.daily_questions', '30 câu luyện tập mỗi ngày', '1', 30],
                    ['ai.daily_requests', '10 lượt hỏi AI mỗi ngày', '1', 10],
                    ['ai.advanced_modes', 'AI phân tích lỗi sai, bài tương tự', '0', null],
                    ['reports.advanced', 'Báo cáo nâng cao cho phụ huynh', '0', null],
                ],
            ],
            [
                'slug' => 'pro-thang', 'name' => 'Pro 1 tháng', 'tier' => 'pro', 'price' => 99000, 'duration_days' => 30,
                'description' => 'Toàn bộ bài học, luyện tập không giới hạn, AI Tutor mỗi ngày.',
                'is_highlighted' => true, 'sort_order' => 2,
                'features' => $pro = [
                    ['display.lessons', 'Toàn bộ bài học Pro', '1', null],
                    ['practice.daily_questions', 'Luyện tập không giới hạn', '1', null],
                    ['ai.daily_requests', '50 lượt hỏi AI mỗi ngày', '1', 50],
                    ['display.path', 'Kiểm tra đầu vào & lộ trình cá nhân', '1', null],
                    ['ai.advanced_modes', 'AI phân tích lỗi sai, bài tương tự', '0', null],
                    ['reports.advanced', 'Báo cáo nâng cao cho phụ huynh', '0', null],
                ],
            ],
            [
                'slug' => 'pro-nam', 'name' => 'Pro 12 tháng', 'tier' => 'pro', 'price' => 990000, 'duration_days' => 365,
                'description' => 'Như Pro 1 tháng, tiết kiệm 2 tháng.', 'sort_order' => 3,
                'features' => $pro,
            ],
            [
                'slug' => 'premium-thang', 'name' => 'Premium 1 tháng', 'tier' => 'premium', 'price' => 199000, 'duration_days' => 30,
                'description' => 'Tất cả của Pro + AI nâng cao + báo cáo chi tiết cho phụ huynh.', 'sort_order' => 4,
                'features' => $premium = [
                    ['display.lessons', 'Toàn bộ bài học Pro & Premium', '1', null],
                    ['practice.daily_questions', 'Luyện tập không giới hạn', '1', null],
                    ['ai.daily_requests', '200 lượt hỏi AI mỗi ngày', '1', 200],
                    ['display.path', 'Kiểm tra đầu vào & lộ trình cá nhân', '1', null],
                    ['ai.advanced_modes', 'AI phân tích lỗi sai, bài tương tự', '1', null],
                    ['reports.advanced', 'Báo cáo nâng cao cho phụ huynh', '1', null],
                ],
            ],
            [
                'slug' => 'premium-nam', 'name' => 'Premium 12 tháng', 'tier' => 'premium', 'price' => 1990000, 'duration_days' => 365,
                'description' => 'Như Premium 1 tháng, tiết kiệm 2 tháng.', 'sort_order' => 5,
                'features' => $premium,
            ],
        ];

        foreach ($packages as $data) {
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
}
