<?php

namespace App\Jobs;

use App\Services\Content\IndexNowService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Gửi một lô URL cho IndexNow — tách ra queue để admin bấm lưu bài không phải chờ API bên ngoài.
 * Không thử lại: IndexNow trả lỗi thường là khoá/miền sai, gửi lại vẫn sai; bot vẫn tự quét qua sitemap.
 */
class SubmitToIndexNow implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /** @param  list<string>  $urls */
    public function __construct(public readonly array $urls) {}

    public function handle(IndexNowService $indexNow): void
    {
        $indexNow->submit($this->urls);
    }
}
