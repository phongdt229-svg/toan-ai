<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\AnalyticsReportService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Lối tắt sang Google Analytics / Tag Manager / Search Console và trạng thái cấu hình đo lường.
 * Chỉ ĐỌC config/site.php — số liệu truy cập nằm ở Google, không kéo về đây (không có API key).
 */
class AnalyticsController extends Controller
{
    public function __construct(private readonly AnalyticsReportService $report) {}

    public function index(Request $request): View
    {
        $days = in_array($request->integer('days'), [7, 28, 90], true) ? $request->integer('days') : 28;

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
            'days' => $days,
            'gaConfigured' => $this->report->isConfigured(),
            'gaReport' => $this->report->overview($days),
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

    /** Nút "Làm mới": bỏ cache 10 phút để xem số mới ngay. */
    public function refresh(): RedirectResponse
    {
        $this->report->forget();

        return back()->with('status', 'Đã làm mới số liệu.');
    }
}
