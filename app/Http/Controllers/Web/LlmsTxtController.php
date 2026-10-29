<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Content\LlmsTxtService;
use Illuminate\Http\Response;

/**
 * /llms.txt cho công cụ AI — nội dung xem LlmsTxtService. Sinh động (không để file tĩnh trong public/)
 * vì có giá gói và bài mới: file tĩnh là thứ đầu tiên bị quên sửa, như robots.txt từng trỏ miền cũ.
 */
class LlmsTxtController extends Controller
{
    public function __invoke(LlmsTxtService $llms): Response
    {
        return response($llms->render(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            // Không chứa gì riêng tư; cho CDN giữ 1 giờ để bot quét dồn dập không chạm tới DB.
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
