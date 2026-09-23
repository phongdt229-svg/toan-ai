<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\View\View;

/**
 * Lối tắt sang Google Analytics / Tag Manager / Search Console và trạng thái cấu hình đo lường.
 * Chỉ ĐỌC config/site.php — số liệu truy cập nằm ở Google, không kéo về đây (không có API key).
 */
class AnalyticsController extends Controller
{
    public function index(): View
    {
        $ga = (string) config('site.google_analytics_id');
        $gtm = (string) config('site.google_tag_manager_id');

        return view('admin.analytics', [
            'tools' => [
                [
                    'name' => 'Google Analytics 4',
                    'id' => $ga,
                    'desc' => 'Lượt truy cập, nguồn khách, trang xem nhiều, chuyển đổi.',
                    'url' => 'https://analytics.google.com/',
                    'icon' => 'bi-bar-chart-line',
                ],
                [
                    'name' => 'Google Tag Manager',
                    'id' => $gtm,
                    'desc' => 'Quản lý thẻ theo dõi mà không cần sửa code.',
                    'url' => 'https://tagmanager.google.com/',
                    'icon' => 'bi-tags',
                ],
                [
                    'name' => 'Search Console',
                    'id' => (string) config('site.google_site_verification'),
                    'desc' => 'Từ khoá, thứ hạng tìm kiếm và tình trạng lập chỉ mục.',
                    'url' => 'https://search.google.com/search-console',
                    'icon' => 'bi-search',
                ],
            ],
            'embedUrl' => $this->embedUrl(),
            'tracking' => $ga !== '' || $gtm !== '',
            'both' => $ga !== '' && $gtm !== '',
        ]);
    }

    /**
     * Chỉ nhúng đúng domain Looker Studio qua https: giá trị đến từ .env nên không phải đầu vào
     * người dùng, nhưng ghi sai/dán nhầm link lạ thì khung này vẫn không được trỏ đi chỗ khác.
     */
    private function embedUrl(): ?string
    {
        $url = trim((string) config('site.looker_studio_embed_url'));
        $parts = parse_url($url);

        if ($url === '' || ($parts['scheme'] ?? '') !== 'https' || ($parts['host'] ?? '') !== 'lookerstudio.google.com') {
            return null;
        }

        return $url;
    }
}
