<?php

namespace App\Services\Admin;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Đọc số liệu truy cập từ GA4 Data API bằng service account (chỉ quyền Người xem, chỉ đọc).
 *
 * Tự ký JWT RS256 và đổi lấy access token thay vì kéo cả google/apiclient (hàng chục MB, hàng trăm file)
 * chỉ để gọi hai endpoint. Không bao giờ ném lỗi ra ngoài: GA hỏng/chưa cấu hình thì trang chỉ ẩn phần này.
 *
 * Số liệu cache 10 phút như AnalyticsService — GA vốn trễ vài giờ, hỏi dồn dập chỉ tốn hạn ngạch.
 */
class AnalyticsReportService
{
    private const SCOPE = 'https://www.googleapis.com/auth/analytics.readonly';

    private const TOKEN_URL = 'https://oauth2.googleapis.com/token';

    private const CACHE_TTL = 600;

    public function isConfigured(): bool
    {
        return preg_match('/^\d+$/', (string) config('site.ga_property_id')) === 1
            && is_readable((string) config('site.ga_credentials_path'));
    }

    /**
     * @return array{totals: array{users: int, sessions: int, views: int}, daily: list<array{label: string, users: int, views: int}>,
     *               pages: list<array{path: string, views: int}>, sources: list<array{label: string, count: int}>}|null
     */
    public function overview(int $days = 28): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        $key = "ga.overview.{$days}";

        if ($cached = Cache::get($key)) {
            return $cached;
        }

        try {
            $report = $this->fetch($days);
        } catch (Throwable $e) {
            // Không cache lỗi: lần tải sau thử lại ngay khi GA hồi phục.
            Log::warning('GA Data API lỗi', ['error' => $e->getMessage()]);

            return null;
        }

        Cache::put($key, $report, self::CACHE_TTL);

        return $report;
    }

    public function forget(): void
    {
        foreach ([7, 28, 90] as $days) {
            Cache::forget("ga.overview.{$days}");
        }
    }

    /** @return array<string, mixed> */
    private function fetch(int $days): array
    {
        $token = $this->accessToken();
        $range = [['startDate' => "{$days}daysAgo", 'endDate' => 'today']];

        $totals = $this->report($token, ['dateRanges' => $range,
            'metrics' => [['name' => 'activeUsers'], ['name' => 'sessions'], ['name' => 'screenPageViews']]]);

        $daily = $this->report($token, ['dateRanges' => $range,
            'dimensions' => [['name' => 'date']],
            'metrics' => [['name' => 'activeUsers'], ['name' => 'screenPageViews']],
            'orderBys' => [['dimension' => ['dimensionName' => 'date']]]]);

        $pages = $this->report($token, ['dateRanges' => $range,
            'dimensions' => [['name' => 'pagePath']], 'metrics' => [['name' => 'screenPageViews']],
            'orderBys' => [['metric' => ['metricName' => 'screenPageViews'], 'desc' => true]], 'limit' => 10]);

        $sources = $this->report($token, ['dateRanges' => $range,
            'dimensions' => [['name' => 'sessionDefaultChannelGroup']], 'metrics' => [['name' => 'sessions']],
            'orderBys' => [['metric' => ['metricName' => 'sessions'], 'desc' => true]], 'limit' => 8]);

        $t = $totals['rows'][0]['metricValues'] ?? [];

        return [
            'totals' => ['users' => (int) ($t[0]['value'] ?? 0), 'sessions' => (int) ($t[1]['value'] ?? 0), 'views' => (int) ($t[2]['value'] ?? 0)],
            'daily' => collect($daily['rows'] ?? [])->map(fn ($r) => [
                'label' => Carbon::createFromFormat('Ymd', $r['dimensionValues'][0]['value'])->format('d/m'),
                'users' => (int) $r['metricValues'][0]['value'],
                'views' => (int) $r['metricValues'][1]['value'],
            ])->all(),
            'pages' => collect($pages['rows'] ?? [])->map(fn ($r) => [
                'path' => (string) $r['dimensionValues'][0]['value'],
                'views' => (int) $r['metricValues'][0]['value'],
            ])->all(),
            'sources' => collect($sources['rows'] ?? [])->map(fn ($r) => [
                'label' => (string) $r['dimensionValues'][0]['value'],
                'count' => (int) $r['metricValues'][0]['value'],
            ])->all(),
        ];
    }

    /** @param  array<string, mixed>  $body */
    private function report(string $token, array $body): array
    {
        $property = config('site.ga_property_id');

        try {
            $response = Http::withToken($token)->timeout(15)
                ->post("https://analyticsdata.googleapis.com/v1beta/properties/{$property}:runReport", $body);
        } catch (ConnectionException $e) {
            throw new \RuntimeException('Không kết nối được GA: '.$e->getMessage());
        }

        if (! $response->successful()) {
            throw new \RuntimeException('GA trả lỗi '.$response->status().': '.($response->json('error.message') ?? ''));
        }

        return (array) $response->json();
    }

    private function accessToken(): string
    {
        $key = json_decode((string) file_get_contents((string) config('site.ga_credentials_path')), true);

        if (! is_array($key) || empty($key['client_email']) || empty($key['private_key'])) {
            throw new \RuntimeException('File khoá service account không hợp lệ.');
        }

        $b64 = fn (string $data) => rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
        $now = time();

        $unsigned = $b64((string) json_encode(['alg' => 'RS256', 'typ' => 'JWT'])).'.'.$b64((string) json_encode([
            'iss' => $key['client_email'],
            'scope' => self::SCOPE,
            'aud' => self::TOKEN_URL,
            'iat' => $now,
            'exp' => $now + 3600,
        ]));

        if (! openssl_sign($unsigned, $signature, $key['private_key'], OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('Không ký được JWT — private_key sai định dạng.');
        }

        $response = Http::asForm()->timeout(15)->post(self::TOKEN_URL, [
            'grant_type' => 'urn:ietf:params:oauth:grant-type:jwt-bearer',
            'assertion' => $unsigned.'.'.$b64($signature),
        ]);

        if (! $response->successful() || blank($response->json('access_token'))) {
            throw new \RuntimeException('Đổi token thất bại: '.($response->json('error_description') ?? $response->status()));
        }

        return (string) $response->json('access_token');
    }
}
