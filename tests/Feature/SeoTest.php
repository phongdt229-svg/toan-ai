<?php

namespace Tests\Feature;

use App\Models\Grade;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Thẻ chia sẻ mạng xã hội + sitemap (§ bổ sung sau roadmap). */
class SeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    public function test_landing_page_has_open_graph_tags(): void
    {
        $response = $this->get('/')->assertOk();

        $response->assertSee('property="og:title"', false)
            ->assertSee('property="og:image"', false)
            ->assertSee('name="twitter:card" content="summary_large_image"', false)
            ->assertSee('og-cover.png', false)
            ->assertSee('Học Toán lớp 1–12 theo đúng chương trình', false);
    }

    public function test_guide_article_shares_its_own_title_and_summary(): void
    {
        $slug = array_key_first(config('guides.articles'));
        $article = config("guides.articles.{$slug}");

        $this->get(route('guides.show', $slug))
            ->assertOk()
            ->assertSee('<meta property="og:title" content="'.e($article['title']).'">', false)
            ->assertSee('<meta property="og:type" content="article">', false)
            ->assertSee(e($article['summary']), false);
    }

    public function test_the_og_cover_image_exists(): void
    {
        $this->assertFileExists(public_path('og-cover.png'));
    }

    public function test_pages_behind_login_are_not_indexed(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $user->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        $this->actingAs($user)->get(route('student.dashboard'))
            ->assertOk()
            ->assertSee('content="noindex,nofollow"', false);

        $this->get('/')->assertSee('content="index,follow"', false);
    }

    // --- Sitemap --------------------------------------------------------------------------

    public function test_sitemap_lists_public_pages_and_every_guide(): void
    {
        $response = $this->get('/sitemap.xml')->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');

        $xml = $response->getContent();
        $this->assertStringContainsString('<loc>'.route('home').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('packages.index').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('guides.index').'</loc>', $xml);
        $this->assertStringContainsString('<loc>'.route('legal.privacy').'</loc>', $xml);

        foreach (array_keys(config('guides.articles')) as $slug) {
            $this->assertStringContainsString('<loc>'.route('guides.show', $slug).'</loc>', $xml);
        }

        // XML hợp lệ thì mới có giá trị với Google.
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_sitemap_never_exposes_pages_behind_login(): void
    {
        $xml = $this->get('/sitemap.xml')->getContent();

        foreach (['/hoc-sinh', '/giao-vien', '/phu-huynh', '/quan-tri', '/tai-khoan'] as $private) {
            $this->assertStringNotContainsString($private.'<', $xml);
        }
    }

    // --- Google Analytics + Search Console -------------------------------------------------

    public function test_analytics_is_off_when_no_measurement_id_is_configured(): void
    {
        // Mặc định ở local/test là trống — lượt truy cập lúc dev không được vào số liệu thật.
        config(['site.google_analytics_id' => '']);

        $this->get('/')->assertOk()->assertDontSee('googletagmanager.com', false);
    }

    public function test_analytics_loads_on_public_and_portal_pages_when_configured(): void
    {
        config(['site.google_analytics_id' => 'G-TEST12345']);

        $this->get('/')
            ->assertOk()
            ->assertSee('https://www.googletagmanager.com/gtag/js?id=G-TEST12345', false)
            ->assertSee('gtag(\'config\', "G-TEST12345")', false);

        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $user->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        $this->actingAs($user)->get(route('student.dashboard'))->assertOk()->assertSee('G-TEST12345', false);
    }

    public function test_tag_manager_loads_with_its_noscript_fallback(): void
    {
        config(['site.google_tag_manager_id' => 'GTM-TEST123']);

        $response = $this->get('/')->assertOk();

        $response->assertSee("'script','dataLayer',\"GTM-TEST123\"", false)
            ->assertSee('https://www.googletagmanager.com/ns.html?id=GTM-TEST123', false);

        // Bản dự phòng cho trình duyệt tắt JS phải nằm ngay sau <body>, không thì GTM bỏ qua.
        $html = $response->getContent();
        $this->assertLessThan(
            strpos($html, 'maintenance-flag') ?: strlen($html),
            strpos($html, 'ns.html?id=GTM-TEST123'),
        );
        $this->assertStringContainsString('<body>', substr($html, 0, strpos($html, 'ns.html')));
    }

    public function test_tag_manager_is_off_when_not_configured(): void
    {
        config(['site.google_tag_manager_id' => '']);

        $this->get('/')->assertOk()->assertDontSee('gtm.js?id=', false)->assertDontSee('ns.html?id=', false);
    }

    public function test_search_console_verification_tag_is_present(): void
    {
        config(['site.google_site_verification' => 'abc123']);

        $this->get('/')->assertOk()
            ->assertSee('<meta name="google-site-verification" content="abc123">', false);
    }

    public function test_the_privacy_policy_discloses_analytics_tracking(): void
    {
        // Bật đo lường mà quên khai báo là chính sách nói sai sự thật — test giữ hai thứ đi cùng nhau.
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertSee('Google Analytics')
            ->assertSee('Cookie phân tích');
    }

    public function test_robots_points_search_engines_at_the_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://', $robots);
        $this->assertStringContainsString('Disallow: /quan-tri', $robots);
    }
}
