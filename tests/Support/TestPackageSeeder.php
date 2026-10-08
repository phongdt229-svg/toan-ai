<?php

namespace Tests\Support;

use Database\Seeders\PackageSeeder;

/**
 * Bộ gói CỐ ĐỊNH cho test — tách khỏi bảng giá kinh doanh (PackageSeeder) để đổi giá bán không làm vỡ
 * hàng chục test về cơ chế thanh toán / cộng nối hạn. Test nào kiểm tra đúng bảng giá thật thì seed PackageSeeder.
 *
 * Có gói tháng (30 ngày) và gói năm cùng hạng để test được việc cộng nối thời hạn.
 */
class TestPackageSeeder extends PackageSeeder
{
    protected function packages(): array
    {
        $pro = [
            ['path.sessions', 'Lộ trình không giới hạn buổi', '1', null],
            ['display.lessons', 'Toàn bộ bài học Pro', '1', null],
            ['practice.daily_questions', 'Luyện tập không giới hạn', '1', null],
            ['ai.daily_requests', '50 lượt hỏi AI mỗi ngày', '1', 50],
            ['ai.image_daily', 'Chụp ảnh đề: 20 lần mỗi ngày', '1', 20],
            ['display.path', 'Kiểm tra đầu vào & lộ trình cá nhân', '1', null],
            ['ai.advanced_modes', 'AI phân tích lỗi sai, bài tương tự', '0', null],
            ['reports.advanced', 'Báo cáo nâng cao cho phụ huynh', '0', null],
        ];
        $premium = [
            ['path.sessions', 'Lộ trình không giới hạn buổi', '1', null],
            ['display.lessons', 'Toàn bộ bài học Pro & Premium', '1', null],
            ['practice.daily_questions', 'Luyện tập không giới hạn', '1', null],
            ['ai.daily_requests', '200 lượt hỏi AI mỗi ngày', '1', 200],
            ['ai.image_daily', 'Chụp ảnh đề: 50 lần mỗi ngày', '1', 50],
            ['display.path', 'Kiểm tra đầu vào & lộ trình cá nhân', '1', null],
            ['ai.advanced_modes', 'AI phân tích lỗi sai, bài tương tự', '1', null],
            ['reports.advanced', 'Báo cáo nâng cao cho phụ huynh', '1', null],
        ];

        return [
            [
                'slug' => 'free', 'name' => 'Free', 'tier' => 'free', 'price' => 0, 'duration_days' => null,
                'description' => 'Bắt đầu học miễn phí, không cần thẻ.', 'is_default' => true, 'sort_order' => 1,
                'features' => [
                    ['path.sessions', 'Học thử 3 buổi theo lộ trình', '1', 3],
                    ['display.lessons', 'Bài học cơ bản của mọi lớp', '1', null],
                    ['practice.daily_questions', '30 câu luyện tập mỗi ngày', '1', 30],
                    ['ai.daily_requests', '10 lượt hỏi AI mỗi ngày', '1', 10],
                    ['ai.image_daily', 'Chụp ảnh đề: 3 lần mỗi ngày', '1', 3],
                    ['ai.advanced_modes', 'AI phân tích lỗi sai, bài tương tự', '0', null],
                    ['reports.advanced', 'Báo cáo nâng cao cho phụ huynh', '0', null],
                ],
            ],
            [
                'slug' => 'pro-thang', 'name' => 'Pro 1 tháng', 'tier' => 'pro', 'price' => 99000, 'duration_days' => 30,
                'description' => 'Toàn bộ bài học, luyện tập không giới hạn, AI Tutor mỗi ngày.',
                'is_highlighted' => true, 'sort_order' => 2, 'features' => $pro,
            ],
            [
                'slug' => 'pro-nam', 'name' => 'Pro 12 tháng', 'tier' => 'pro', 'price' => 990000, 'duration_days' => 365,
                'description' => 'Như Pro 1 tháng, tiết kiệm 2 tháng.', 'sort_order' => 3, 'features' => $pro,
            ],
            [
                'slug' => 'premium-thang', 'name' => 'Premium 1 tháng', 'tier' => 'premium', 'price' => 199000, 'duration_days' => 30,
                'description' => 'Tất cả của Pro + AI nâng cao + báo cáo chi tiết cho phụ huynh.', 'sort_order' => 4, 'features' => $premium,
            ],
            [
                'slug' => 'premium-nam', 'name' => 'Premium 12 tháng', 'tier' => 'premium', 'price' => 1990000, 'duration_days' => 365,
                'description' => 'Như Premium 1 tháng, tiết kiệm 2 tháng.', 'sort_order' => 5, 'features' => $premium,
            ],
        ];
    }
}
