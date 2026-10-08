<?php

namespace Tests\Feature\AI;

use App\Models\AiConversation;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;

/** Chụp ảnh đề (đặc tả module 7): AI chỉ CHÉP đề, học sinh sửa rồi mới gửi vào chat. */
class ImageSolverTest extends AiTestCase
{
    private function upload(int $w = 1200, int $h = 900): UploadedFile
    {
        return UploadedFile::fake()->image('de-bai.jpg', $w, $h);
    }

    public function test_photo_is_transcribed_not_solved(): void
    {
        $student = $this->makeStudent();

        $this->actingAs($student)
            ->post(route('api.ai.read-image'), ['image' => $this->upload()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.confidence', 'high')
            ->assertJsonPath('data.problem', 'Tính $\\frac{1}{2} + \\frac{1}{3}$.');

        $request = $this->fake()->lastRequest();
        $this->assertSame('read_image', $request->task);
        $this->assertCount(1, $request->images);
        $this->assertStringStartsWith('data:image/jpeg;base64,', $request->images[0]);
        $this->assertStringContainsString('KHÔNG giải', $this->lastPrompt());

        // Chỉ giữ chữ đã chép — không lưu ảnh.
        $conversation = AiConversation::where('mode', 'read_image')->firstOrFail();
        $this->assertSame('[Ảnh đề bài]', $conversation->messages()->where('role', 'user')->value('content'));
    }

    public function test_large_photo_is_redrawn_smaller_before_sending(): void
    {
        $this->actingAs($this->makeStudent())
            ->post(route('api.ai.read-image'), ['image' => $this->upload(3200, 1200)], ['Accept' => 'application/json'])
            ->assertOk();

        $uri = $this->fake()->lastRequest()->images[0];
        $size = getimagesizefromstring(base64_decode(substr($uri, strlen('data:image/jpeg;base64,'))));

        $this->assertSame(1600, $size[0]);
        $this->assertSame(IMAGETYPE_JPEG, $size[2]);
    }

    public function test_non_image_file_is_rejected(): void
    {
        $fake = UploadedFile::fake()->createWithContent('de.png', '<?php echo "hack";');

        $this->actingAs($this->makeStudent())
            ->post(route('api.ai.read-image'), ['image' => $fake], ['Accept' => 'application/json'])
            ->assertUnprocessable();

        $this->assertNull($this->fake()->lastRequest());
    }

    public function test_photo_without_a_problem_asks_to_retake(): void
    {
        $this->fake()->push(['problem' => '', 'confidence' => 'low']);

        $this->actingAs($this->makeStudent())
            ->post(route('api.ai.read-image'), ['image' => $this->upload()], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonPath('reason', 'unreadable');
    }

    public function test_daily_photo_limit_is_separate_from_chat_quota(): void
    {
        config(['ai.image_daily_default' => 1]);
        $student = $this->makeStudent();

        $this->actingAs($student)
            ->post(route('api.ai.read-image'), ['image' => $this->upload()], ['Accept' => 'application/json'])
            ->assertOk();

        $this->actingAs($student)
            ->post(route('api.ai.read-image'), ['image' => $this->upload()], ['Accept' => 'application/json'])
            ->assertStatus(402)
            ->assertJsonPath('reason', 'upgrade_required');

        // Hết lượt chụp ảnh nhưng vẫn chat chữ bình thường.
        $this->actingAs($student)->postJson(route('api.ai.chat'), ['message' => 'Phân số là gì?'])->assertOk();
    }

    public function test_openai_receives_the_image_as_a_content_part(): void
    {
        config(['ai.provider' => 'openai', 'ai.openai.api_key' => 'k', 'ai.openai.model' => 'gpt-4o-mini']);
        Http::fake(['api.openai.com/*' => Http::response([
            'model' => 'gpt-4o-mini',
            'choices' => [['message' => ['content' => json_encode(['problem' => 'Tìm $x$: $2x + 3 = 7$', 'confidence' => 'medium'])]]],
            'usage' => ['prompt_tokens' => 900, 'completion_tokens' => 20],
        ])]);

        $this->actingAs($this->makeStudent())
            ->post(route('api.ai.read-image'), ['image' => $this->upload()], ['Accept' => 'application/json'])
            ->assertOk()
            ->assertJsonPath('data.confidence', 'medium');

        Http::assertSent(function (HttpRequest $r) {
            $user = collect($r['messages'])->last(fn ($m) => $m['role'] === 'user');

            return is_array($user['content'])
                && $user['content'][1]['type'] === 'image_url'
                && str_starts_with($user['content'][1]['image_url']['url'], 'data:image/jpeg;base64,')
                && $r['temperature'] == 0
                && $r['response_format']['type'] === 'json_object';
        });
    }
}
