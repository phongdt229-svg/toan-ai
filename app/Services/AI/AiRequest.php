<?php

namespace App\Services\AI;

final readonly class AiRequest
{
    /**
     * @param  array<int, array{role: string, content: string}>  $messages
     * @param  string  $task  chế độ nghiệp vụ (hint, explain…) — FakeProvider dựa vào đây để trả mẫu phù hợp
     * @param  bool  $json  yêu cầu model trả về JSON hợp lệ
     * @param  array<string, mixed>  $meta  tham số phụ (vd số câu cần sinh) — không gửi lên provider
     */
    public function __construct(
        public array $messages,
        public string $task,
        public int $maxTokens = 700,
        public float $temperature = 0.4,
        public bool $json = false,
        public array $meta = [],
    ) {}
}
