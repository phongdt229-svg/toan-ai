<?php

namespace App\Services\AI;

final readonly class AiResponse
{
    public function __construct(
        public string $content,
        public string $model,
        public int $tokensIn,
        public int $tokensOut,
        public int $latencyMs,
    ) {}

    /**
     * Parse JSON từ câu trả lời. Model đôi khi bọc JSON trong ```json … ``` dù đã yêu cầu JSON thuần.
     *
     * @return array<string, mixed>
     *
     * @throws AiProviderException
     */
    public function json(): array
    {
        $text = trim($this->content);
        $text = preg_replace('/^```(?:json)?\s*|\s*```$/i', '', $text) ?? $text;

        $data = json_decode($text, true);

        if (! is_array($data)) {
            throw new AiProviderException('AI trả về dữ liệu không đúng định dạng.', 'invalid_json');
        }

        return $data;
    }
}
