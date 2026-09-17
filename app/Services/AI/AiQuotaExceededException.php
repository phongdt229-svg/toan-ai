<?php

namespace App\Services\AI;

use RuntimeException;

class AiQuotaExceededException extends RuntimeException
{
    public function __construct(public readonly int $limit)
    {
        parent::__construct("Bạn đã dùng hết {$limit} lượt hỏi AI hôm nay. Mai quay lại nhé, hoặc nâng cấp gói để có thêm lượt.");
    }
}
