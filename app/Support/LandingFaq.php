<?php

namespace App\Support;

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
        // Nội dung theo mẫu V6 (docs/hoctoan-layout-v6) — cố ý thận trọng: chưa hứa số lượt/giá, vì quyền lợi
        // gói trả phí chưa công bố trên trang chủ.
        return [
            [
                'Con học yếu hoặc học khá giỏi có dùng được không?',
                'Website hướng đến học sinh lớp '.config('learning.grade_min').'–'.config('learning.grade_max').' với mục tiêu khác nhau. '
                .'Việc lựa chọn nội dung cần dựa trên kết quả đánh giá và khả năng thực tế của con.',
            ],
            [
                'Lộ trình minh họa có phải kết quả đánh giá của con không?',
                'Không. Nội dung theo lớp chỉ giúp bố mẹ hình dung cách tổ chức bài học. '
                .'Lộ trình cá nhân cần dựa trên bài đánh giá năng lực thực tế.',
            ],
            [
                'A.I có thay thế giáo viên không?',
                'Không. A.I là công cụ hỗ trợ học và luyện tập. Phụ huynh nên phối hợp với giáo viên '
                .'khi con gặp khó khăn kéo dài hoặc nội dung phức tạp.',
            ],
            [
                'Tôi có cần trả tiền trước khi đăng ký không?',
                'Biểu mẫu chọn lớp trên trang này không thu tiền. Quyền lợi và điều kiện thanh toán cần được công bố rõ '
                .'trước khi mua gói trả phí.',
            ],
        ];
    }
}
