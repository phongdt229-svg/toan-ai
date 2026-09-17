<?php

namespace App\Jobs;

use App\Models\AiGenerationDraft;
use App\Services\AI\ContentGeneratorService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Sinh nội dung AI chạy nền: một lượt tạo 10–20 câu mất 20–60 giây, không thể bắt giáo viên chờ trên request.
 */
class GenerateAiContent implements ShouldQueue
{
    use Queueable;

    /** Không tự thử lại — mỗi lần gọi là một lần tính tiền. Giáo viên tự bấm tạo lại nếu lỗi. */
    public int $tries = 1;

    public int $timeout = 180;

    public function __construct(public readonly int $draftId) {}

    public function handle(ContentGeneratorService $generator): void
    {
        $draft = AiGenerationDraft::with('user')->find($this->draftId);

        if ($draft && $draft->status === AiGenerationDraft::STATUS_PENDING) {
            $generator->generate($draft);
        }
    }
}
