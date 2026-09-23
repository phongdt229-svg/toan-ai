<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderException;
use App\Services\AI\AiRequest;
use App\Services\AI\AiResponse;
use App\Services\AI\Contracts\AiProviderInterface;

/**
 * Provider giả cho dev và test — không gọi mạng, không tốn tiền, kết quả đoán trước được.
 *
 * Test có thể:
 * - push(): xếp sẵn câu trả lời cho lượt gọi tiếp theo
 * - failNext(): cho lượt gọi tiếp theo ném lỗi
 * - $calls: xem lại các request đã nhận (kiểm tra prompt)
 */
class FakeProvider implements AiProviderInterface
{
    /** @var array<int, string|array<string, mixed>|AiProviderException> */
    private array $queue = [];

    /** @var array<int, AiRequest> */
    public array $calls = [];

    public function name(): string
    {
        return 'fake';
    }

    /** @param  string|array<string, mixed>  $content  mảng sẽ được encode thành JSON */
    public function push(string|array $content): self
    {
        $this->queue[] = $content;

        return $this;
    }

    public function failNext(?AiProviderException $e = null): self
    {
        $this->queue[] = $e ?? AiProviderException::unavailable();

        return $this;
    }

    public function lastRequest(): ?AiRequest
    {
        return $this->calls[array_key_last($this->calls)] ?? null;
    }

    public function complete(AiRequest $request): AiResponse
    {
        $this->calls[] = $request;

        $next = array_shift($this->queue) ?? $this->defaultFor($request);

        if ($next instanceof AiProviderException) {
            throw $next;
        }

        $content = is_array($next) ? json_encode($next, JSON_UNESCAPED_UNICODE) : $next;
        $prompt = collect($request->messages)->pluck('content')->implode("\n");

        return new AiResponse(
            content: $content,
            model: 'fake',
            // Ước lượng ~4 ký tự / token để số liệu usage trông hợp lý khi dev.
            tokensIn: (int) ceil(mb_strlen($prompt) / 4),
            tokensOut: (int) ceil(mb_strlen($content) / 4),
            latencyMs: 5,
        );
    }

    /** @return string|array<string, mixed> */
    private function defaultFor(AiRequest $request): string|array
    {
        return match ($request->task) {
            'hint' => 'Gợi ý: em thử tìm **mẫu số chung** của hai phân số trước nhé. Mẫu số chung nhỏ nhất của 2 và 3 là bao nhiêu?',
            'explain' => "Ta làm từng bước:\n1. Quy đồng: \$\\frac{1}{2} = \\frac{3}{6}\$, \$\\frac{1}{3} = \\frac{2}{6}\$.\n2. Cộng tử số: \$3 + 2 = 5\$.\n3. Kết quả: \$\\frac{5}{6}\$.",
            'check_answer' => 'Em làm đúng hướng rồi. Chú ý bước quy đồng: phải nhân **cả tử và mẫu** với cùng một số.',
            'similar_exercise' => [
                'problem' => 'Tính $\\frac{1}{4} + \\frac{1}{6}$.',
                'answer' => '$\\frac{5}{12}$',
                'solution' => 'Mẫu chung 12: $\\frac{3}{12} + \\frac{2}{12} = \\frac{5}{12}$.',
            ],
            'analyze_mistake' => [
                'misconception' => 'Cộng thẳng tử với tử, mẫu với mẫu.',
                'knowledge_gap' => 'Quy đồng mẫu số',
                'explanation' => 'Hai phân số khác mẫu biểu diễn các phần có kích thước khác nhau, nên phải quy đồng trước khi cộng.',
                'hint' => 'Tìm mẫu số chung nhỏ nhất rồi đổi cả hai phân số về mẫu đó.',
            ],
            'generate_questions' => ['questions' => $this->fakeQuestions($request->meta)],
            'generate_lesson' => ['sections' => [
                ['type' => 'theory', 'title' => 'Quy tắc', 'content' => '<p>Nội dung lý thuyết mẫu với công thức $a + b$.</p>'],
                ['type' => 'example', 'title' => 'Ví dụ', 'content' => '<p>$1 + 1 = 2$</p>'],
                ['type' => 'common_mistake', 'title' => 'Lỗi thường gặp', 'content' => '<p>Quên quy đồng.</p>'],
            ]],
            'rewrite' => '<p>Nội dung đã được viết lại cho dễ hiểu hơn.</p>',
            'placement_analysis' => 'Em nắm khá chắc phần tính toán cơ bản. Phần cần củng cố là **quy đồng mẫu số** — cô đã xếp các bài đó vào giai đoạn đầu của lộ trình. Cứ học đều mỗi ngày một buổi, em sẽ tiến bộ nhanh thôi!',
            default => 'Chào em! Cô là trợ lý Toán. Em đang vướng ở bước nào?',
        };
    }

    /**
     * @param  array<string, mixed>  $meta
     * @return array<int, array<string, mixed>>
     */
    private function fakeQuestions(array $meta): array
    {
        $plan = $meta['plan'] ?? ['easy' => 1, 'medium' => 1, 'hard' => 0];
        $items = [];
        $n = 1;

        foreach ($plan as $difficulty => $count) {
            for ($i = 0; $i < $count; $i++, $n++) {
                $items[] = [
                    'type' => 'single_choice',
                    'difficulty' => $difficulty,
                    'content' => "Câu mẫu {$n}: tính \$\\frac{1}{{$n}} + \\frac{1}{{$n}}\$.",
                    'options' => ["\$\\frac{2}{{$n}}\$", "\$\\frac{1}{{$n}}\$", '$\\frac{2}{'.(2 * $n).'}$'],
                    'correct' => [0],
                    'explanation' => 'Cùng mẫu số nên cộng tử số.',
                ];
            }
        }

        return $items;
    }
}
