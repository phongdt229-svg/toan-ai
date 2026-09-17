<?php

namespace Tests\Feature\AI;

use App\Services\AI\AiProviderException;
use App\Services\AI\AiRequest;
use App\Services\AI\AiResponse;
use App\Services\AI\AiUsageGuard;
use App\Services\AI\Providers\OpenAiProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** Kiểm tra provider thật bằng Http::fake — không gọi mạng, không tốn tiền. */
class OpenAiProviderTest extends TestCase
{
    private function provider(?string $key = 'sk-test'): OpenAiProvider
    {
        return new OpenAiProvider($key, 'gpt-4o-mini', 'https://api.openai.test/v1', 10);
    }

    private function request(bool $json = false): AiRequest
    {
        return new AiRequest(
            messages: [['role' => 'system', 'content' => 'sys'], ['role' => 'user', 'content' => 'hi']],
            task: 'hint',
            maxTokens: 300,
            json: $json,
        );
    }

    public function test_sends_expected_payload_and_parses_usage(): void
    {
        Http::fake(['api.openai.test/*' => Http::response([
            'model' => 'gpt-4o-mini-2024-07-18',
            'choices' => [['message' => ['content' => 'Gợi ý nè']]],
            'usage' => ['prompt_tokens' => 120, 'completion_tokens' => 30],
        ])]);

        $response = $this->provider()->complete($this->request(json: true));

        $this->assertSame('Gợi ý nè', $response->content);
        $this->assertSame(120, $response->tokensIn);
        $this->assertSame(30, $response->tokensOut);

        Http::assertSent(function (Request $req) {
            return $req->url() === 'https://api.openai.test/v1/chat/completions'
                && $req->hasHeader('Authorization', 'Bearer sk-test')
                && $req['model'] === 'gpt-4o-mini'
                && $req['max_tokens'] === 300
                && $req['response_format'] === ['type' => 'json_object']
                && $req['messages'][1]['content'] === 'hi';
        });
    }

    public function test_missing_key_fails_without_any_http_call(): void
    {
        Http::fake();

        try {
            $this->provider(null)->complete($this->request());
            $this->fail('Phải ném lỗi khi thiếu key.');
        } catch (AiProviderException $e) {
            $this->assertSame('not_configured', $e->reason);
        }

        Http::assertNothingSent();
    }

    public function test_invalid_key_maps_to_not_configured(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'bad key']], 401)]);

        $this->expectExceptionObject(AiProviderException::notConfigured());
        $this->provider()->complete($this->request());
    }

    public function test_server_errors_are_retried_then_reported_as_unavailable(): void
    {
        Http::fake(['*' => Http::response(['error' => ['message' => 'overloaded']], 503)]);

        try {
            $this->provider()->complete($this->request());
            $this->fail('Phải ném lỗi.');
        } catch (AiProviderException $e) {
            $this->assertSame('unavailable', $e->reason);
            // Thông điệp cho học sinh không lộ chi tiết kỹ thuật.
            $this->assertStringNotContainsString('overloaded', $e->getMessage());
        }

        Http::assertSentCount(2); // retry(2) của Laravel = tổng 2 lần gọi: 1 lần + 1 lần thử lại
    }

    public function test_json_parsing_tolerates_markdown_fences(): void
    {
        $response = new AiResponse("```json\n{\"a\": 1}\n```", 'm', 0, 0, 0);

        $this->assertSame(['a' => 1], $response->json());
    }

    public function test_cost_estimate_matches_model_by_prefix(): void
    {
        $guard = app(AiUsageGuard::class);

        // gpt-4o-mini: 0.15$/1M input, 0.60$/1M output — không được khớp nhầm sang giá gpt-4o.
        $cost = $guard->estimateCost(new AiResponse('x', 'gpt-4o-mini-2024-07-18', 1_000_000, 1_000_000, 0));

        $this->assertEqualsWithDelta(0.75, $cost, 0.0001);
    }
}
