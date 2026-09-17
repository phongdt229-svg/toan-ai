<?php

namespace App\Services;

use App\Models\Package;
use RuntimeException;

/**
 * Tính năng không có trong gói hiện tại → trả 402 kèm gợi ý gói nâng cấp,
 * để giao diện hiện paywall thay vì lỗi trống (PROJECT_PLAN §7).
 */
class FeatureLockedException extends RuntimeException
{
    public function __construct(string $message, public readonly ?Package $upgradeTo = null)
    {
        parent::__construct($message);
    }
}
