<?php

namespace App\Services\AI\Contracts;

use App\Services\AI\AiProviderException;
use App\Services\AI\AiRequest;
use App\Services\AI\AiResponse;

/**
 * Mọi nghiệp vụ AI chỉ nói chuyện qua interface này — đổi nhà cung cấp không sửa TutorService.
 */
interface AiProviderInterface
{
    /** @throws AiProviderException */
    public function complete(AiRequest $request): AiResponse;

    /** Tên hiển thị trên trang admin, vd "openai:gpt-4o-mini". */
    public function name(): string;
}
