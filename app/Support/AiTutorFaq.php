<?php

namespace App\Support;

/**
 * Câu hỏi thường gặp của trang "Học Toán với AI" — một nguồn cho khối FAQ hiển thị, JSON-LD FAQPage
 * và /llms.txt (cùng lý do với LandingFaq).
 *
 * Mỗi câu trả lời phải khớp hành vi THẬT của AI Tutor: PromptBuilder::MODE_RULES (gợi ý không lộ đáp án,
 * đọc ảnh chỉ chép đề), TutorAccessGuard (khoá khi làm bài kiểm tra, bài giao chưa nộp chỉ gợi ý),
 * GradingService chấm đúng/sai chứ không phải AI, Chính sách bảo mật (phụ huynh không xem chat riêng).
 * Đổi một trong các chỗ đó thì soát lại mục tương ứng ở đây.
 */
class AiTutorFaq
{
    /**
     * @return list<array{0: string, 1: string}> [câu hỏi, câu trả lời]
     */
    public static function items(): array
    {
        return [
            [
                'Học Toán với AI có khác dùng app chụp ảnh ra đáp án không?',
                'Khác. App chụp ảnh đưa ngay lời giải nên con chép được mà không hiểu. AI Tutor của '.config('site.brand')
                .' chỉ gợi ý bước tiếp theo, hỏi lại để con tự nghĩ, và chỉ giải thích trọn lời giải sau khi con đã làm bài.',
            ],
            [
                'AI có tự chấm điểm bài của con không?',
                'Không. Đúng hay sai do hệ thống chấm theo đáp án giáo viên soạn sẵn. AI chỉ nhận xét cách làm: bước nào đúng, '
                .'bước nào sai và sửa thế nào — nên điểm số không phụ thuộc vào việc AI đoán đúng hay sai.',
            ],
            [
                'Con có dùng AI để gian lận khi làm bài kiểm tra được không?',
                'Không. Trong lúc làm bài kiểm tra AI Tutor bị khoá hoàn toàn. Với bài giáo viên giao mà con chưa nộp, '
                .'AI chỉ được gợi ý chứ không giải thích lời giải; nộp bài xong mới hỏi chi tiết được.',
            ],
            [
                'Chụp ảnh đề bài thì AI làm gì?',
                'AI chỉ chép lại đề từ ảnh để con kiểm tra và sửa nếu đọc sai, không giải luôn. Ảnh không được lưu lại, '
                .'chỉ giữ phần chữ của đề.',
            ],
            [
                'AI có trả lời chuyện ngoài môn Toán không?',
                'AI Tutor được giới hạn trong môn Toán và từ chối câu hỏi ngoài lề. Các lượt hỏi đáng ngờ được ghi lại '
                .'để quản trị viên xem xét.',
            ],
            [
                'Phụ huynh có xem được con hỏi AI những gì không?',
                'Phụ huynh đã liên kết xem được tiến độ, điểm và phần con còn yếu, nhưng không đọc được nội dung trò chuyện '
                .'riêng của con với AI Tutor — để con thoải mái hỏi cả những câu mình ngại hỏi.',
            ],
            [
                'Học Toán với AI có mất phí không?',
                // Không ghi số lượt/gói nào có gì: quota nằm trong bảng package_features, admin đổi được bất cứ lúc nào.
                'Bắt đầu miễn phí, không cần thẻ — gói Free đã hỏi được AI Tutor với số lượt giới hạn mỗi ngày. '
                .'Số lượt hỏi và các tính năng AI nâng cao (phân tích lỗi sai, tạo bài tương tự) tuỳ theo gói, xem ở trang Gói học.',
            ],
        ];
    }
}
