<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\CookieConsentController as Consent;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Grade;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Content\BlogService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
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

    /** @return array{0: User, 1: BlogCategory} */
    private function blogAuthor(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN);

        return [$admin, BlogCategory::create(['name' => 'Giới thiệu', 'slug' => 'gioi-thieu'])];
    }

    public function test_sitemap_declares_the_cover_image_of_blog_posts_that_have_one(): void
    {
        [$admin, $category] = $this->blogAuthor();

        app(BlogService::class)->create([
            'blog_category_id' => $category->id, 'title' => 'Bài có ảnh bìa cho sitemap',
            'content' => 'Nội dung.', 'status' => 'published',
        ], $admin, UploadedFile::fake()->image('a.jpg', 800, 450));
        $post = BlogPost::firstOrFail();

        $xml = $this->get('/sitemap.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $xml);
        $this->assertStringContainsString('<image:loc>'.e($post->coverUrl()).'</image:loc>', $xml);
        $this->assertStringContainsString('<image:title>Bài có ảnh bìa cho sitemap</image:title>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_sitemap_has_no_image_tag_for_posts_without_a_cover(): void
    {
        [$admin, $category] = $this->blogAuthor();

        app(BlogService::class)->create([
            'blog_category_id' => $category->id, 'title' => 'Bài không có ảnh bìa',
            'content' => 'Nội dung.', 'status' => 'published',
        ], $admin);

        $xml = $this->get('/sitemap.xml')->getContent();

        $this->assertStringNotContainsString('<image:image>', $xml);
    }

    // --- RSS Tin tức -------------------------------------------------------------------------

    public function test_rss_feed_lists_published_posts_newest_first_and_excludes_drafts(): void
    {
        [$admin, $category] = $this->blogAuthor();
        $blog = app(BlogService::class);

        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài cũ hơn', 'excerpt' => 'Tóm tắt cũ.', 'content' => 'Nội dung.', 'status' => 'published'], $admin);
        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài nháp không nên lộ', 'content' => 'Nội dung.', 'status' => 'draft'], $admin);
        $this->travel(1)->hours();
        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài mới nhất', 'excerpt' => 'Tóm tắt mới.', 'content' => 'Nội dung.', 'status' => 'published'], $admin);

        $response = $this->get(route('blog.feed'))->assertOk();
        $response->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8');

        $xml = $response->getContent();
        $this->assertNotFalse(simplexml_load_string($xml));
        $this->assertStringNotContainsString('Bài nháp không nên lộ', $xml);
        $this->assertStringContainsString('Tóm tắt mới.', $xml);
        $this->assertStringContainsString(route('blog.show', 'bai-moi-nhat'), $xml);

        // Bài mới nhất phải đứng trước bài cũ hơn trong feed.
        $this->assertLessThan(strpos($xml, 'Bài cũ hơn'), strpos($xml, 'Bài mới nhất'));
    }

    public function test_blog_index_links_to_the_rss_feed(): void
    {
        $this->get(route('blog.index'))->assertOk()
            ->assertSee('type="application/rss+xml"', false)
            ->assertSee(route('blog.feed'), false);
    }

    // --- Google News Sitemap -----------------------------------------------------------------

    public function test_news_sitemap_only_lists_posts_published_within_the_last_48_hours(): void
    {
        [$admin, $category] = $this->blogAuthor();
        $blog = app(BlogService::class);

        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài quá 48 giờ', 'content' => 'Nội dung.', 'status' => 'published'], $admin);
        BlogPost::where('title', 'Bài quá 48 giờ')->update(['published_at' => now()->subHours(49)]);

        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài nháp trong 48 giờ', 'content' => 'Nội dung.', 'status' => 'draft'], $admin);

        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài mới trong 48 giờ', 'content' => 'Nội dung.', 'status' => 'published'], $admin);

        $xml = $this->get('/sitemap-news.xml')->assertOk()->getContent();

        $this->assertStringContainsString('xmlns:news="http://www.google.com/schemas/sitemap-news/0.9"', $xml);
        $this->assertStringContainsString('<news:title>Bài mới trong 48 giờ</news:title>', $xml);
        $this->assertStringNotContainsString('Bài quá 48 giờ', $xml);
        $this->assertStringNotContainsString('Bài nháp trong 48 giờ', $xml);
        $this->assertStringContainsString('<news:language>vi</news:language>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_news_sitemap_is_empty_when_nothing_was_published_recently(): void
    {
        $xml = $this->get('/sitemap-news.xml')->assertOk()->getContent();

        $this->assertStringNotContainsString('<url>', $xml);
        $this->assertNotFalse(simplexml_load_string($xml));
    }

    public function test_robots_also_points_at_the_news_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://toanai.vn/sitemap-news.xml', $robots);
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

        // Từ đợt 23/09 công cụ đo lường chỉ nạp khi người dùng đã đồng ý cookie.
        $this->withCookie(Consent::COOKIE, Consent::ACCEPTED);

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

        $this->withCookie(Consent::COOKIE, Consent::ACCEPTED)
            ->actingAs($user)->get(route('student.dashboard'))->assertOk()->assertSee('G-TEST12345', false);
    }

    public function test_tag_manager_loads_with_its_noscript_fallback(): void
    {
        config(['site.google_tag_manager_id' => 'GTM-TEST123']);

        $response = $this->withCookie(Consent::COOKIE, Consent::ACCEPTED)->get('/')->assertOk();

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
