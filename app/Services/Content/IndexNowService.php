<?php

namespace App\Services\Content;

use App\Jobs\SubmitToIndexNow;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Báo URL vừa đổi cho IndexNow (api.indexnow.org chuyển tiếp sang Bing, Yandex…). GEO: ChatGPT Search,
 * Copilot dựa trên chỉ mục Bing — bài mới được biết trong vài phút thay vì vài ngày.
 *
 * Không bao giờ làm hỏng thao tác của admin: gửi qua queue, lỗi mạng/khoá sai chỉ ghi log.
 */
class IndexNowService
{
    public const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function enabled(): bool
    {
        return preg_match('/^[A-Za-z0-9-]{8,128}$/', (string) config('site.indexnow_key')) === 1;
    }

    /**
     * Xếp hàng gửi. URL phải tính SẴN ở đây (trong request của admin, đúng miền đang chạy) — job chạy trong
     * worker thì route() lấy theo APP_URL, sai APP_URL là gửi URL miền khác và bị từ chối.
     *
     * @param  list<string>  $urls
     */
    public function queue(array $urls): void
    {
        if ($this->enabled() && $urls !== []) {
            SubmitToIndexNow::dispatch(array_values(array_unique($urls)));
        }
    }

    /** @param  list<string>  $urls */
    public function submit(array $urls): bool
    {
        if (! $this->enabled() || $urls === []) {
            return false;
        }

        $scheme = parse_url($urls[0], PHP_URL_SCHEME) ?: 'https';
        $host = (string) parse_url($urls[0], PHP_URL_HOST);
        // IndexNow từ chối cả lô nếu có URL khác miền với `host`.
        $urls = array_values(array_filter($urls, fn (string $url) => parse_url($url, PHP_URL_HOST) === $host));

        try {
            $response = Http::timeout(10)->acceptJson()->post(self::ENDPOINT, [
                'host' => $host,
                'key' => config('site.indexnow_key'),
                'keyLocation' => $scheme.'://'.$host.'/indexnow-key.txt',
                'urlList' => $urls,
            ]);
        } catch (Throwable $e) {
            Log::warning('IndexNow: không gửi được', ['error' => $e->getMessage()]);

            return false;
        }

        // 200 = đã nhận, 202 = đã nhận nhưng chưa kiểm khoá xong (lần đầu) — cả hai đều ổn.
        if (! in_array($response->status(), [200, 202], true)) {
            Log::warning('IndexNow: bị từ chối', ['status' => $response->status(), 'urls' => count($urls)]);

            return false;
        }

        return true;
    }
}
