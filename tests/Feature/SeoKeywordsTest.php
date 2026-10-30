<?php

namespace Tests\Feature;

use App\Support\AiTutorFaq;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Trang đích cho hai cụm từ khoá chính (docs/SEO_KEYWORDS.md): "học toán online" (trang chủ + trang lớp)
 * và "học toán với AI" (/hoc-toan-voi-ai). Canh để đổi giao diện không làm rơi mất từ khoá khỏi title/H1,
 * và trang mới luôn có mặt ở sitemap, llms.txt, menu.
 */
class SeoKeywordsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    public function test_home_title_and_h1_target_hoc_toan_online(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<title>[^<]*Học Toán Online[^<]*</title>#u', $html);
        preg_match('#<h1[^>]*>(.*?)</h1>#su', $html, $h1);
        $this->assertStringContainsString('Học Toán online', strip_tags($h1[1] ?? ''));
    }

    public function test_ai_learning_page_targets_hoc_toan_voi_ai(): void
    {
        $html = $this->get(route('ai-learning'))->assertOk()->getContent();

        $this->assertMatchesRegularExpression('#<title>Học Toán với AI[^<]*</title>#u', $html);
        $this->assertStringContainsString('<meta name="robots" content="index,follow">', $html);
        preg_match('#<h1[^>]*>(.*?)</h1>#su', $html, $h1);
        $this->assertStringContainsString('Học Toán với AI', strip_tags($h1[1] ?? ''));
    }

    public function test_ai_learning_faq_markup_matches_what_is_shown(): void
    {
        $html = $this->get(route('ai-learning'))->getContent();

        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
        $faq = collect($m[1])->flatMap(fn ($json) => json_decode(trim($json), true)['@graph'] ?? [])
            ->firstWhere('@type', 'FAQPage');

        $this->assertNotNull($faq);
        $this->assertSame(array_column(AiTutorFaq::items(), 0), array_column($faq['mainEntity'], 'name'));

        foreach (AiTutorFaq::items() as [$question]) {
            $this->assertStringContainsString(e($question), $html);
        }
    }

    public function test_ai_learning_page_is_linked_from_menu_sitemap_and_llms_txt(): void
    {
        $url = route('ai-learning');

        $this->get('/')->assertSee('href="'.$url.'"', false);
        $this->get(route('sitemap'))->assertSee('<loc>'.$url.'</loc>', false);
        $this->assertStringContainsString('('.$url.')', $this->get('/llms.txt')->getContent());
    }

    // --- E-E-A-T: tác giả, ngày cập nhật, nguồn, trích dẫn chuyên gia -------------------------

    public function test_pages_declare_an_author_and_update_date(): void
    {
        config(['site.editor_name' => '', 'site.editor_title' => '']);

        foreach (['/', route('ai-learning')] as $page) {
            $html = $this->get($page)->getContent();

            $this->assertStringContainsString('<meta name="author" content="Đội ngũ biên soạn '.config('site.brand').'">', $html, $page);
            $this->assertMatchesRegularExpression('#Cập nhật: <time datetime="\d{4}-\d{2}-\d{2}">#u', $html, $page);

            $webPage = collect($this->graph($html))->firstWhere('@type', 'WebPage');
            $this->assertSame(url('/').'#organization', $webPage['author']['@id'], $page);
            $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}$/', $webPage['dateModified'], $page);
        }
    }

    public function test_a_real_editor_is_declared_as_a_person(): void
    {
        config(['site.editor_name' => 'Nguyễn Văn A', 'site.editor_title' => 'Giáo viên Toán THCS']);

        $html = $this->get(route('ai-learning'))->getContent();

        $this->assertStringContainsString('<meta name="author" content="Nguyễn Văn A">', $html);
        $this->assertStringContainsString('Nguyễn Văn A</span>, Giáo viên Toán THCS', $html);
        $author = collect($this->graph($html))->firstWhere('@type', 'WebPage')['author'];
        $this->assertSame(['@type' => 'Person', 'name' => 'Nguyễn Văn A', 'jobTitle' => 'Giáo viên Toán THCS', 'worksFor' => ['@id' => url('/').'#organization']], $author);
    }

    public function test_ai_learning_page_cites_outside_sources_and_an_expert(): void
    {
        $html = $this->get(route('ai-learning'))->getContent();

        $this->assertStringContainsString('href="https://doi.org/10.1073/pnas.2422633122"', $html);
        $this->assertStringContainsString('Thông tư 32/2018/TT-BGDĐT', $html);
        $this->assertMatchesRegularExpression('#<blockquote[^>]*cite="https://[^"]+"#', $html);
        $this->assertStringContainsString('Hamsa Bastani', $html);
        // Link nguồn không gắn nofollow — đó là trích dẫn mình đứng ra bảo đảm, khác link mạng xã hội.
        $this->assertDoesNotMatchRegularExpression('#href="https://doi\.org[^"]*"[^>]*nofollow#', $html);
    }

    public function test_home_links_to_its_sources(): void
    {
        $this->get('/')
            ->assertSee('href="https://doi.org/10.1073/pnas.2422633122"', false)
            ->assertSee('Thông tư 32/2018/TT-BGDĐT');
    }

    public function test_ai_learning_page_never_hard_codes_quota_or_price(): void
    {
        // Số lượt/giá nằm trong DB (packages, package_features) — viết tay ở trang tĩnh là sớm muộn lệch.
        $text = strip_tags($this->get(route('ai-learning'))->getContent());

        $this->assertDoesNotMatchRegularExpression('/\d+\s*(lượt|₫|đồng)/u', $text);
    }

    private function graph(string $html): array
    {
        preg_match_all('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);

        return collect($m[1])->flatMap(function ($json) {
            $data = json_decode(trim($json), true) ?? [];

            return $data['@graph'] ?? [$data];
        })->all();
    }
}
