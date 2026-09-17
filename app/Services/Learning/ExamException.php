<?php

namespace App\Services\Learning;

use RuntimeException;

/** Lỗi nghiệp vụ khi làm đề — message hiển thị thẳng cho học sinh. */
class ExamException extends RuntimeException
{
    public static function notAvailable(): self
    {
        return new self('Đề kiểm tra này hiện không mở.');
    }

    public static function noAttemptsLeft(int $max): self
    {
        return new self("Bạn đã dùng hết {$max} lượt làm đề này.");
    }

    public static function timeUp(): self
    {
        return new self('Đã hết giờ làm bài. Bài của bạn đã được nộp tự động.');
    }

    public static function notInAttempt(): self
    {
        return new self('Câu hỏi không thuộc lượt làm bài này.');
    }

    public static function alreadySubmitted(): self
    {
        return new self('Bài đã được nộp.');
    }

    public static function empty(): self
    {
        return new self('Đề kiểm tra chưa có câu hỏi.');
    }
}
