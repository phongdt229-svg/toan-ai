<?php

namespace App\Services\AI;

use RuntimeException;

/**
 * Lỗi phía nhà cung cấp AI. `getMessage()` luôn an toàn để hiện cho người dùng —
 * chi tiết kỹ thuật (mã lỗi HTTP, body) chỉ ghi log.
 */
class AiProviderException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason = 'provider_error')
    {
        parent::__construct($message);
    }

    public static function notConfigured(): self
    {
        return new self('AI Tutor đang tạm thời không khả dụng.', 'not_configured');
    }

    public static function unavailable(): self
    {
        return new self('AI Tutor đang bận, bạn thử lại sau ít phút nhé.', 'unavailable');
    }
}
