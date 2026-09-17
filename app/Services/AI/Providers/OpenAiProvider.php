<?php

namespace App\Services\AI\Providers;

use App\Services\AI\AiProviderException;
use App\Services\AI\AiRequest;
use App\Services\AI\AiResponse;
use App\Services\AI\Contracts\AiProviderInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class OpenAiProvider implements AiProviderInterface
{
    public function __construct(
        private readonly ?string $apiKey,
        private readonly string $model,
        private readonly string $baseUrl,
        private readonly int $timeout,
    ) {}

    public function name(): string
    {
        return "openai:{$this->model}";
    }

    public function complete(AiRequest $request): AiResponse
    {
        if (blank($this->apiKey)) {
            throw AiProviderException::notConfigured();
        }

        $payload = [
            'model' => $this->model,
            'messages' => $request->messages,
            'max_tokens' => $request->maxTokens,
            'temperature' => $request->temperature,
        ];

        if ($request->json) {
            $payload['response_format'] = ['type' => 'json_object'];
        }

        $started = hrtime(true);

        try {
            $response = Http::withToken($this->apiKey)
                ->acceptJson()
                ->timeout($this->timeout)
                // Thử lại MỘT lần (retry(2) = tổng 2 lần gọi) khi bị giới hạn tốc độ hoặc lỗi tạm thời phía server.
                // Không thử nhiều hơn: mỗi lần có thể chờ tới timeout, học sinh đang đợi trên màn hình.
                ->retry(2, 800, fn ($e) => $e instanceof ConnectionException
                    || ($e instanceof RequestException && in_array($e->response->status(), [429, 500, 502, 503], true)), throw: false)
                ->post("{$this->baseUrl}/chat/completions", $payload);
        } catch (ConnectionException $e) {
            Log::warning('OpenAI connection failed', ['task' => $request->task, 'error' => $e->getMessage()]);

            throw AiProviderException::unavailable();
        }

        if ($response->failed()) {
            // Không log header/payload: có API key và nội dung học sinh.
            Log::warning('OpenAI request failed', [
                'task' => $request->task,
                'status' => $response->status(),
                'error' => $response->json('error.message'),
            ]);

            throw $response->status() === 401
                ? AiProviderException::notConfigured()
                : AiProviderException::unavailable();
        }

        $content = (string) $response->json('choices.0.message.content', '');

        if ($content === '') {
            throw AiProviderException::unavailable();
        }

        return new AiResponse(
            content: $content,
            model: (string) $response->json('model', $this->model),
            tokensIn: (int) $response->json('usage.prompt_tokens', 0),
            tokensOut: (int) $response->json('usage.completion_tokens', 0),
            latencyMs: (int) ((hrtime(true) - $started) / 1_000_000),
        );
    }
}
