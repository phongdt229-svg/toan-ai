<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\Admin\AnalyticsReportService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/** GA4 Data API — Google được giả bằng Http::fake, không gọi mạng thật. */
class AnalyticsReportTest extends TestCase
{
    use RefreshDatabase;

    private string $keyFile;

    private $publicKey;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();

        $pair = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
        openssl_pkey_export($pair, $private);
        $this->publicKey = openssl_pkey_get_details($pair)['key'];

        $this->keyFile = tempnam(sys_get_temp_dir(), 'ga');
        file_put_contents($this->keyFile, json_encode(['client_email' => 'reader@proj.iam.gserviceaccount.com', 'private_key' => $private]));

        config(['site.ga_property_id' => '123456789', 'site.ga_credentials_path' => $this->keyFile]);
    }

    protected function tearDown(): void
    {
        @unlink($this->keyFile);
        parent::tearDown();
    }

    private function fakeGoogle(): void
    {
        Http::fake([
            'oauth2.googleapis.com/token' => Http::response(['access_token' => 'tok-abc', 'expires_in' => 3600]),
            'analyticsdata.googleapis.com/*' => function (HttpRequest $r) {
                $dims = collect($r['dimensions'] ?? [])->pluck('name')->first();

                return Http::response(match ($dims) {
                    'date' => ['rows' => [['dimensionValues' => [['value' => '20260920']], 'metricValues' => [['value' => '5'], ['value' => '12']]]]],
                    'pagePath' => ['rows' => [['dimensionValues' => [['value' => '/goi-hoc']], 'metricValues' => [['value' => '40']]]]],
                    'sessionDefaultChannelGroup' => ['rows' => [['dimensionValues' => [['value' => 'Organic Search']], 'metricValues' => [['value' => '30']]]]],
                    default => ['rows' => [['metricValues' => [['value' => '17'], ['value' => '25'], ['value' => '90']]]]],
                });
            },
        ]);
    }

    private function admin(): User
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        return $admin;
    }

    public function test_reads_report_using_a_valid_signed_service_account_jwt(): void
    {
        $this->fakeGoogle();

        $report = app(AnalyticsReportService::class)->overview(28);

        $this->assertSame(['users' => 17, 'sessions' => 25, 'views' => 90], $report['totals']);
        $this->assertSame([['label' => '20/09', 'users' => 5, 'views' => 12]], $report['daily']);
        $this->assertSame('/goi-hoc', $report['pages'][0]['path']);
        $this->assertSame('Organic Search', $report['sources'][0]['label']);

        // JWT gửi lên Google phải ký đúng bằng khoá riêng: verify bằng khoá công khai.
        Http::assertSent(function (HttpRequest $r) {
            if (! str_contains($r->url(), 'oauth2.googleapis.com')) {
                return false;
            }

            [$h, $c, $sig] = explode('.', $r['assertion']);
            $b64d = fn ($s) => base64_decode(strtr($s, '-_', '+/'));
            $claims = json_decode($b64d($c), true);

            return openssl_verify("$h.$c", $b64d($sig), $this->publicKey, OPENSSL_ALGO_SHA256) === 1
                && $claims['iss'] === 'reader@proj.iam.gserviceaccount.com'
                && $claims['scope'] === 'https://www.googleapis.com/auth/analytics.readonly'
                && $r['grant_type'] === 'urn:ietf:params:oauth:grant-type:jwt-bearer';
        });

        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'properties/123456789:runReport')
            && $r->hasHeader('Authorization', 'Bearer tok-abc'));
    }

    public function test_result_is_cached_so_repeat_views_do_not_hit_google(): void
    {
        $this->fakeGoogle();

        app(AnalyticsReportService::class)->overview(28);
        $calls = count(Http::recorded());
        app(AnalyticsReportService::class)->overview(28);

        $this->assertSame($calls, count(Http::recorded()));
    }

    public function test_not_configured_returns_null_without_any_request(): void
    {
        Http::fake();
        config(['site.ga_property_id' => '', 'site.ga_credentials_path' => '']);

        $this->assertNull(app(AnalyticsReportService::class)->overview());
        Http::assertNothingSent();
    }

    public function test_property_id_must_be_numeric(): void
    {
        config(['site.ga_property_id' => 'G-QJDV1HXSGX']);

        $this->assertFalse(app(AnalyticsReportService::class)->isConfigured());
    }

    public function test_google_failure_never_breaks_the_page_and_is_not_cached(): void
    {
        Http::fake(['oauth2.googleapis.com/token' => Http::response(['error' => 'invalid_grant', 'error_description' => 'Bad'], 400)]);

        $this->assertNull(app(AnalyticsReportService::class)->overview());

        $this->actingAs($this->admin())->get(route('admin.analytics.index'))
            ->assertOk()->assertSee('chưa đọc được số liệu');

        // Google hồi phục → lần sau đọc được ngay, không kẹt cache lỗi.
        Http::swap(new Factory);
        $this->fakeGoogle();
        $this->assertNotNull(app(AnalyticsReportService::class)->overview());
    }

    public function test_admin_page_shows_numbers_and_refresh_clears_the_cache(): void
    {
        $this->fakeGoogle();
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.analytics.index'))
            ->assertOk()->assertSee('/goi-hoc')->assertSee('Organic Search')->assertSee('ga-daily', false);

        $this->assertTrue(Cache::has('ga.overview.28'));
        $this->post(route('admin.analytics.refresh'))->assertRedirect();
        $this->assertFalse(Cache::has('ga.overview.28'));
    }

    public function test_only_admin_can_refresh(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $student = User::factory()->create();
        $student->assignRole('student');

        $this->actingAs($student)->post(route('admin.analytics.refresh'))->assertForbidden();
    }
}
