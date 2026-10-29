<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\Content\IndexNowService;
use Illuminate\Http\Response;

/**
 * /indexnow-key.txt — IndexNow tải file này để xác minh mình sở hữu miền (keyLocation trong mỗi lần gửi).
 * Chưa cấu hình khoá thì 404: không để lộ một file rỗng trông như đã bật.
 */
class IndexNowKeyController extends Controller
{
    public function __invoke(IndexNowService $indexNow): Response
    {
        abort_unless($indexNow->enabled(), 404);

        return response((string) config('site.indexnow_key'), 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
