<?php

namespace App\Services\AI;

/**
 * Rào chắn thứ hai cho AI Tutor (§10). PromptBuilder đã yêu cầu model tự từ chối
 * câu hỏi ngoài lề Toán học, nhưng model vẫn có thể bị dẫn dắt lạc đề. Lớp này không
 * chặn câu trả lời (đoán sai sẽ chặn nhầm câu hỏi Toán hợp lệ) — chỉ NHẬN DIỆN các lượt
 * đáng ngờ để ghi lại cho quản trị xem lại ở trang AI usage / audit log.
 */
class ScopeGuard
{
    /** Học sinh gõ trúng một trong các cụm này → tin nhắn nhiều khả năng ngoài lề Toán học. */
    private const OFF_TOPIC_KEYWORDS = [
        'chính trị', 'tôn giáo', 'người yêu', 'bạn trai', 'bạn gái', 'yêu đương',
        'viết code', 'lập trình', 'ngữ văn', 'lịch sử', 'địa lý', 'dịch tiếng anh',
        'sức khoẻ', 'sức khỏe', 'uống thuốc', 'bị bệnh', 'thời sự', 'bóng đá',
        'xem phim', 'ca sĩ', 'số điện thoại', 'mật khẩu',
    ];

    /** Cụm PromptBuilder yêu cầu model dùng khi từ chối đúng cách — xem đây là rào chắn đã hoạt động. */
    private const REFUSAL_MARKER = 'không thuộc phạm vi toán học';

    /**
     * @return array{keyword: string}|null null = không có gì đáng ngờ (không cần ghi log).
     */
    public function flag(string $userMessage, string $aiReply): ?array
    {
        $keyword = $this->matchedKeyword($userMessage);

        if ($keyword === null) {
            return null;
        }

        // Model đã từ chối đúng cách theo hướng dẫn — rào chắn đầu tiên hoạt động, không cần cảnh báo.
        if (str_contains(mb_strtolower($aiReply), self::REFUSAL_MARKER)) {
            return null;
        }

        return ['keyword' => $keyword];
    }

    private function matchedKeyword(string $message): ?string
    {
        $normalized = mb_strtolower($message);

        foreach (self::OFF_TOPIC_KEYWORDS as $keyword) {
            if (str_contains($normalized, $keyword)) {
                return $keyword;
            }
        }

        return null;
    }
}
