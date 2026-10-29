<?php

namespace Tests\Feature;

use App\Jobs\SubmitToIndexNow;
use App\Models\BlogCategory;
use App\Models\BlogPost;
use App\Models\Package;
use App\Models\Role;
use App\Models\User;
use App\Services\Content\BlogService;
use App\Services\Content\IndexNowService;
use App\Support\LandingFaq;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\TestPackageSeeder;
use Tests\TestCase;

/**
 * GEO — để công cụ AI (ChatGPT, Claude, Perplexity, AI Overviews) đọc đúng và trích dẫn site:
 * /llms.txt, structured data, IndexNow.
 */
class GeoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    // --- /llms.txt ------------------------------------------------------------------------

    public function test_llms_txt_is_plain_markdown_with_the_brand_and_main_pages(): void
    {
        config(['site.brand' => 'TOÁN AI', 'site.alternate_names' => ['TOÁN AI', 'Học Toán Online']]);

        $response = $this->get('/llms.txt')->assertOk()->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $text = $response->getContent();

        $this->assertStringStartsWith("# TOÁN AI\n\n> ", $text);
        // Tên trùng tên chính không lặp lại trong danh sách tên phụ.
        $this->assertStringContainsString('(còn gọi là Học Toán Online)', $text);
        $this->assertStringContainsString('- [Gói học và bảng giá]('.route('packages.index').')', $text);
        $this->assertStringContainsString('## Optional', $text);
    }

    public function test_llms_txt_lists_every_guide_with_its_link(): void
    {
        $text = $this->get('/llms.txt')->getContent();

        foreach (config('guides.articles') as $slug => $article) {
            $this->assertStringContainsString('['.$article['title'].']('.route('guides.show', $slug).')', $text);
        }
    }

    public function test_llms_txt_prices_come_from_the_packages_table(): void
    {
        $this->seed(TestPackageSeeder::class);
        $package = Package::where('slug', 'pro-thang')->firstOrFail();
        $package->update(['price' => 123000]);

        $this->get('/llms.txt')->assertSee('123.000₫ / 1 tháng', false);

        $package->update(['is_active' => false]);
        $this->get('/llms.txt')->assertDontSee('123.000₫', false);
    }

    public function test_llms_txt_faq_is_the_same_as_the_landing_page(): void
    {
        $text = $this->get('/llms.txt')->getContent();
        $landing = $this->get('/')->getContent();

        foreach (LandingFaq::items() as [$question, $answer]) {
            $this->assertStringContainsString('### '.$question."\n\n".$answer, $text);
            $this->assertStringContainsString(e($question), $landing);
        }
    }

    public function test_llms_txt_lists_published_posts_only(): void
    {
        [$admin, $category] = $this->blogAuthor();
        $blog = app(BlogService::class);
        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài [đã] đăng', 'excerpt' => "Dòng một\nDòng hai", 'content' => 'x', 'status' => 'published'], $admin);
        $blog->create(['blog_category_id' => $category->id, 'title' => 'Bài nháp bí mật', 'content' => 'x', 'status' => 'draft'], $admin);

        $text = $this->get('/llms.txt')->getContent();

        // Ngoặc vuông và xuống dòng do admin gõ không được làm vỡ cú pháp danh sách Markdown.
        $this->assertStringContainsString('- [Bài (đã) đăng]('.route('blog.show', 'bai-da-dang').'): Dòng một Dòng hai', $text);
        $this->assertStringNotContainsString('Bài nháp bí mật', $text);
    }

    // --- Structured data ------------------------------------------------------------------

    public function test_guide_page_declares_its_steps_as_howto(): void
    {
        $slug = array_key_first(config('guides.articles'));
        $article = config("guides.articles.$slug");

        $html = $this->get(route('guides.show', $slug))->assertOk()->getContent();
        $graph = $this->graph($html);

        $howTo = collect($graph)->firstWhere('@type', 'HowTo');
        $this->assertSame($article['title'], $howTo['name']);
        $this->assertCount(count($article['steps']), $howTo['step']);
        $this->assertSame($article['steps'][0][0], $howTo['step'][0]['name']);
        $this->assertContains('BreadcrumbList', collect($graph)->pluck('@type'));

        // Link từng bước trong JSON-LD phải trỏ tới anchor có thật trên trang.
        $this->assertStringEndsWith('#buoc-1', $howTo['step'][0]['url']);
        $this->assertStringContainsString('id="buoc-1"', $html);
    }

    public function test_organization_declares_alternate_names_and_only_real_social_profiles(): void
    {
        config([
            'site.brand' => 'MATH AI',
            'site.alternate_names' => ['TOÁN AI', 'Học Toán Online'],
            'site.social' => ['facebook' => 'https://facebook.com/hoctoanonline', 'youtube' => 'https://youtube.com/@test', 'x' => ''],
        ]);

        $org = collect($this->graph($this->get('/')->getContent()))->firstWhere('@type', 'EducationalOrganization');

        $this->assertSame(['TOÁN AI', 'Học Toán Online'], $org['alternateName']);
        $this->assertSame(['https://facebook.com/hoctoanonline'], $org['sameAs']);
        $this->assertSame('Việt Nam', $org['areaServed']['name']);
    }

    public function test_organization_omits_same_as_when_no_social_profile_is_configured(): void
    {
        config(['site.social' => ['facebook' => '', 'youtube' => '']]);

        $org = collect($this->graph($this->get('/')->getContent()))->firstWhere('@type', 'EducationalOrganization');

        $this->assertArrayNotHasKey('sameAs', $org);
    }

    // --- IndexNow -------------------------------------------------------------------------

    public function test_indexnow_is_off_without_a_key(): void
    {
        config(['site.indexnow_key' => '']);
        Queue::fake();
        [$admin, $category] = $this->blogAuthor();

        $this->get('/indexnow-key.txt')->assertNotFound();
        app(BlogService::class)->create(['blog_category_id' => $category->id, 'title' => 'Bài mới', 'content' => 'x', 'status' => 'published'], $admin);

        Queue::assertNothingPushed();
    }

    public function test_key_file_proves_ownership_when_configured(): void
    {
        config(['site.indexnow_key' => 'abc123def456']);

        $this->get('/indexnow-key.txt')->assertOk()->assertSeeText('abc123def456');
    }

    public function test_publishing_unpublishing_and_deleting_a_post_queue_its_url(): void
    {
        config(['site.indexnow_key' => 'abc123def456']);
        Queue::fake();
        [$admin, $category] = $this->blogAuthor();
        $blog = app(BlogService::class);

        $draft = $blog->create(['blog_category_id' => $category->id, 'title' => 'Nháp', 'content' => 'x', 'status' => 'draft'], $admin);
        Queue::assertNothingPushed();

        $blog->update($draft, ['blog_category_id' => $category->id, 'title' => 'Nháp', 'content' => 'x', 'status' => 'published']);
        Queue::assertPushed(SubmitToIndexNow::class, fn ($job) => $job->urls === [route('blog.show', 'nhap'), route('blog.index')]);

        $blog->update($draft->refresh(), ['blog_category_id' => $category->id, 'title' => 'Nháp', 'content' => 'x', 'status' => 'draft']);
        $blog->destroy(BlogPost::create(['blog_category_id' => $category->id, 'title' => 'Xoá', 'slug' => 'xoa', 'content' => 'x', 'status' => 'published', 'published_at' => now(), 'created_by' => $admin->id]));

        // Xuất bản + rút về nháp + xoá bài đang công khai. Bài nháp bị xoá thì không báo gì.
        Queue::assertPushed(SubmitToIndexNow::class, 3);
    }

    public function test_job_sends_the_documented_payload(): void
    {
        config(['site.indexnow_key' => 'abc123def456']);
        Http::fake([IndexNowService::ENDPOINT => Http::response('', 202)]);

        $ok = app(IndexNowService::class)->submit(['https://hoctoanonline.com/tin-tuc/a', 'https://other.example/b']);

        $this->assertTrue($ok);
        Http::assertSent(fn (Request $r) => $r->url() === IndexNowService::ENDPOINT
            && $r['host'] === 'hoctoanonline.com'
            && $r['key'] === 'abc123def456'
            && $r['keyLocation'] === 'https://hoctoanonline.com/indexnow-key.txt'
            // URL khác miền bị bỏ — IndexNow từ chối cả lô nếu lẫn miền khác.
            && $r['urlList'] === ['https://hoctoanonline.com/tin-tuc/a']);
    }

    public function test_indexnow_failure_never_breaks_anything(): void
    {
        config(['site.indexnow_key' => 'abc123def456']);
        Http::fake([IndexNowService::ENDPOINT => Http::response('Forbidden', 403)]);

        $this->assertFalse(app(IndexNowService::class)->submit(['https://hoctoanonline.com/tin-tuc/a']));

        Http::fake(fn () => throw new ConnectionException('timeout'));
        $this->assertFalse(app(IndexNowService::class)->submit(['https://hoctoanonline.com/tin-tuc/a']));
    }

    public function test_privacy_policy_declares_indexnow_only_when_it_is_on(): void
    {
        config(['site.indexnow_key' => '']);
        $this->get(route('legal.privacy'))->assertDontSee('IndexNow');

        config(['site.indexnow_key' => 'abc123def456']);
        $this->get(route('legal.privacy'))->assertSee('IndexNow');
    }

    // --- Câu chữ về thanh toán -------------------------------------------------------------

    public function test_payment_wording_follows_the_enabled_gateways(): void
    {
        config(['payment.methods' => ['vnpay', 'momo']]);
        $faq = collect(LandingFaq::items())->firstWhere(0, 'Thanh toán và gia hạn ra sao?')[1];
        $this->assertStringStartsWith('Thanh toán qua VNPAY hoặc MoMo.', $faq);
        $this->get('/')->assertSee('Thanh toán qua VNPAY hoặc MoMo');

        config(['payment.methods' => ['momo']]);
        $this->get('/llms.txt')->assertSee('Thanh toán qua MoMo.')->assertDontSee('VNPAY hoặc');
    }

    // --- Helpers --------------------------------------------------------------------------

    /** @return list<array<string, mixed>> mọi node trong các khối JSON-LD có @graph */
    private function graph(string $html): array
    {
        preg_match_all('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);

        return collect($m[1])->flatMap(fn ($json) => json_decode(trim($json), true)['@graph'] ?? [])->all();
    }

    private function blogAuthor(): array
    {
        $admin = User::factory()->create();
        $admin->assignRole(Role::ADMIN);

        return [$admin, BlogCategory::create(['name' => 'Giới thiệu', 'slug' => 'gioi-thieu'])];
    }
}
