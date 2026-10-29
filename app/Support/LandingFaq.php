<?php

namespace App\Support;

use App\Services\Payment\PaymentGatewayManager;

/**
 * Câu hỏi thường gặp của trang chủ — MỘT nguồn cho cả khối FAQ hiển thị, JSON-LD FAQPage và /llms.txt.
 * Trước đây mảng nằm thẳng trong partial Blade; công cụ AI đọc llms.txt mà nhận câu trả lời khác trang
 * thật là tự mâu thuẫn, nên gom về đây.
 *
 * Mỗi câu trả lời phải đúng với sản phẩm đang chạy — cùng luật với hero: không hứa thứ chưa có.
 * Sửa tính năng nào thì soát lại mục tương ứng ở đây.
 */
class LandingFaq
{
    /**
     * @return list<array{0: string, 1: string}> [câu hỏi, câu trả lời]
     */
    public static function items(): array
    {
        return [
            [
                'Không trả tiền thì dùng được gì?',
                'Gói Free cho phép học bài, luyện tập mỗi ngày và hỏi AI Tutor với số lượt giới hạn. '
                .'Không cần thẻ tín dụng, không tự động trừ tiền. Nâng cấp lúc nào thấy cần thì nâng.',
            ],
            [
                'Con tôi học lớp mấy thì dùng được?',
                'Từ lớp '.config('learning.grade_min').' đến lớp '.config('learning.grade_max').'. Chương trình chia theo chương và chủ đề bám sát chương trình phổ thông, '
                .'nên con học tới đâu là mở đúng phần đó.',
            ],
            [
                'AI có làm bài hộ con không?',
                'Không. AI Tutor gợi ý từng bước và giải thích chỗ sai để con tự làm được, không đưa sẵn đáp án. '
                .'Trong lúc con đang làm bài kiểm tra thì AI bị khoá hoàn toàn.',
            ],
            [
                'Tôi theo dõi việc học của con thế nào?',
                'Phụ huynh liên kết với tài khoản của con bằng mã con chia sẻ, rồi xem tiến độ, điểm và phần con '
                .'còn yếu. Mỗi tuần hệ thống gửi email báo cáo học tập cho phụ huynh đã liên kết.',
            ],
            [
                'Thanh toán và gia hạn ra sao?',
                'Thanh toán qua '.app(PaymentGatewayManager::class)->enabledLabel().'. Gói được kích hoạt ngay khi cổng thanh toán xác nhận. Mua thêm cùng hạng khi gói cũ '
                .'còn hạn thì thời gian được cộng nối tiếp, không mất ngày nào.',
            ],
            [
                'Dữ liệu học tập của con có an toàn không?',
                'Chúng tôi không bán dữ liệu cá nhân và không dùng dữ liệu học tập của học sinh để chạy quảng cáo. '
                .'Bạn xem chi tiết ở Chính sách bảo mật, và yêu cầu xoá tài khoản bất cứ lúc nào.',
            ],
        ];
    }
}
