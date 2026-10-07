<?php

namespace Tests\Feature\Infrastructure;

use App\Http\Middleware\MinifyHtml;
use Database\Seeders\GradeSeeder;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Tests\TestCase;

/**
 * Nén HTML (kế hoạch 27/09) — gọi thẳng middleware với $next giả, không qua route thật,
 * để soạn được đúng HTML cần kiểm (có <script>/<pre>/comment) mà không phụ thuộc view thật.
 */
class MinifyHtmlTest extends TestCase
{
    private function response(string $html, string $contentType = 'text/html; charset=UTF-8'): Response
    {
        return response($html, 200, ['Content-Type' => $contentType]);
    }

    private function renderThrough(string $html, bool $enabled, string $contentType = 'text/html; charset=UTF-8'): string
    {
        config(['site.minify_html' => $enabled]);

        $response = (new MinifyHtml)->handle(
            Request::create('/'),
            fn () => $this->response($html, $contentType),
        );

        return $response->getContent();
    }

    public function test_collapses_whitespace_between_tags_and_strips_comments_when_enabled(): void
    {
        $html = "<!DOCTYPE html>\n<html>\n  <body>\n    <!-- ghi chú -->\n    <p>Xin   chào</p>\n  </body>\n</html>";

        $out = $this->renderThrough($html, enabled: true);

        $this->assertStringNotContainsString('<!-- ghi chú -->', $out);
        $this->assertStringNotContainsString("\n", $out);
        $this->assertStringContainsString('<p>Xin chào</p>', $out); // nhiều khoảng trắng trong text gộp còn 1
        $this->assertStringContainsString('<body><p>', $out); // khoảng trắng thuần giữa thẻ bị bỏ
    }

    public function test_leaves_html_untouched_when_disabled(): void
    {
        $html = "<html>\n  <body>\n    <p>Xin   chào</p>\n  </body>\n</html>";

        $this->assertSame($html, $this->renderThrough($html, enabled: false));
    }

    public function test_script_style_pre_and_textarea_content_survive_byte_for_byte(): void
    {
        $html = '<html><body>'
            .'<script>if (a < b) {   console.log("  giữ nguyên  ");   }</script>'
            .'<style>.a{color:  red;}</style>'
            .'<pre>  dòng có   khoảng trắng có ý nghĩa  </pre>'
            .'<textarea>  giữ nguyên luôn  </textarea>'
            .'</body></html>';

        $out = $this->renderThrough($html, enabled: true);

        $this->assertStringContainsString('if (a < b) {   console.log("  giữ nguyên  ");   }', $out);
        $this->assertStringContainsString('.a{color:  red;}', $out);
        $this->assertStringContainsString('  dòng có   khoảng trắng có ý nghĩa  ', $out);
        $this->assertStringContainsString('  giữ nguyên luôn  ', $out);
    }

    public function test_keeps_conditional_comments(): void
    {
        $html = '<html><body><!--[if lt IE 9]><p>IE cũ</p><![endif]--></body></html>';

        $out = $this->renderThrough($html, enabled: true);

        $this->assertStringContainsString('<!--[if lt IE 9]>', $out);
    }

    public function test_non_html_responses_are_never_touched_even_when_enabled(): void
    {
        $xml = "<?xml version=\"1.0\"?>\n<urlset>\n  <url>\n    <loc>a</loc>\n  </url>\n</urlset>\n";

        $this->assertSame($xml, $this->renderThrough($xml, enabled: true, contentType: 'application/xml; charset=UTF-8'));
    }

    public function test_real_page_is_minified_end_to_end_when_the_env_flag_is_on(): void
    {
        config(['site.minify_html' => true]);

        $this->seed(GradeSeeder::class);

        $response = $this->get('/');
        $response->assertOk();

        $html = $response->getContent();
        $this->assertStringNotContainsString(">\n<", $html);
        // JSON-LD (kế hoạch SEO 27/09) vẫn phải là JSON hợp lệ sau khi nén.
        preg_match('/<script type="application\/ld\+json">(.*?)<\/script>/s', $html, $m);
        $this->assertNotEmpty($m);
        $this->assertIsArray(json_decode($m[1], true));
    }
}
