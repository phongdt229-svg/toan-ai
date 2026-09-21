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

    public function test_robots_points_search_engines_at_the_sitemap(): void
    {
        $robots = file_get_contents(public_path('robots.txt'));

        $this->assertStringContainsString('Sitemap: https://', $robots);
        $this->assertStringContainsString('Disallow: /quan-tri', $robots);
    }
}
