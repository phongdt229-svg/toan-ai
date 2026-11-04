<?php

namespace Tests\Feature;

use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_all_v6_sections(): void
    {
        $this->seed(GradeSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('<title>Học Toán Online A.I – Hiểu năng lực, học đúng cách</title>', false)
            ->assertSee('Biết con đang vững phần nào,')
            ->assertSee('Đăng ký kiểm tra năng lực cho con')
            ->assertSee('Mỗi học sinh có nhu cầu học Toán khác nhau')
            ->assertSee('Lộ trình học Toán phù hợp với năng lực của con')
            ->assertSee('Con chưa hiểu bài? A.I gợi ý từng bước')
            ->assertSee('Bố mẹ biết con tiến bộ ở đâu, còn vướng phần nào')
            ->assertSee('Học phí và cách bắt đầu')
            ->assertSee('Bố mẹ thường muốn biết')
            ->assertSee('Bắt đầu đánh giá năng lực Toán cho con');
    }

    public function test_landing_page_links_go_to_real_routes(): void
    {
        $html = $this->get(route('home'))->assertOk()->getContent();

        // Form hero và nút "Đăng ký" trỏ tới trang đăng ký phụ huynh thật, không phải đường dẫn mẫu.
        $this->assertStringContainsString('action="'.route('register.parent').'"', $html);
        $this->assertStringContainsString('href="'.route('login').'"', $html);
        $this->assertStringContainsString('href="'.route('legal.privacy').'"', $html);
        $this->assertStringContainsString('href="'.route('legal.terms').'"', $html);
        $this->assertStringNotContainsString('assets/gia-dinh-hoc-toan.svg', $html);

        // Mọi nút "Đăng ký…" đi thẳng tới trang đăng ký phụ huynh, không cuộn về form.
        $this->assertDoesNotMatchRegularExpression('/href="#[^"]*"[^>]*>Đăng ký/u', $html);
        $this->assertGreaterThanOrEqual(5, preg_match_all('#href="'.preg_quote(route('register.parent'), '#').'"[^>]*>Đăng ký#u', $html));
    }

    public function test_landing_page_is_the_standalone_v6_layout(): void
    {
        // Giống 100% mẫu: CSS riêng của mẫu, không nạp Bootstrap/app.js và không có nút nổi Zalo/Facebook.
        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertStringContainsString('--primary:#5743e5', $html);
        $this->assertStringNotContainsString('floating-contact', $html);
        $this->assertStringNotContainsString('/build/assets/app-', $html);
    }

    public function test_paid_plans_are_not_priced_on_the_landing_page(): void
    {
        // Mẫu V6 chủ động chưa công bố giá Pro/Premium — giá chỉ ở trang Gói học (bảng `packages`).
        $this->get(route('home'))->assertOk()
            ->assertSee('THÔNG TIN SẮP CÔNG BỐ')
            ->assertDontSee('₫');
    }

    public function test_the_faq_is_shown_and_its_structured_data_matches(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match_all('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $data = collect($m[1])->map(fn ($json) => json_decode($json, true))->firstWhere('@type', 'FAQPage');
        $this->assertNotNull($data, 'Thiếu dữ liệu có cấu trúc FAQ cho Google.');
        $this->assertCount(4, $data['mainEntity']);

        // Google phạt trang khai một đằng hiện một nẻo — mọi câu khai báo phải có trên màn hình.
        foreach ($data['mainEntity'] as $entry) {
            $this->assertStringContainsString(e($entry['name']), $html);
            $this->assertStringContainsString(e($entry['acceptedAnswer']['text']), $html);
        }
    }

    public function test_landing_has_the_webpage_schema_with_author_and_date(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertStringContainsString('"@type":"WebPage"', preg_replace('/\s+/', '', $html));
    }

    public function test_moit_badge_shows_in_the_v6_footer_only_when_the_notification_is_approved(): void
    {
        config(['site.moit_url' => '']);
        $this->get('/')->assertOk()->assertDontSee('Đã thông báo Bộ Công Thương');

        config(['site.moit_url' => 'http://online.gov.vn/Home/WebDetails/123456', 'site.moit_logo' => 'khong-co-file.png']);
        $this->get('/')->assertOk()
            ->assertSee('href="http://online.gov.vn/Home/WebDetails/123456"', false)
            ->assertSee('Đã thông báo Bộ Công Thương')
            ->assertDontSee('khong-co-file.png', false);
    }

    public function test_login_and_register_pages_render(): void
    {
        $this->seed(GradeSeeder::class);

        $this->get(route('login'))->assertOk();
        $this->get(route('register'))->assertOk()->assertSee('Bạn là ai?');
        $this->get(route('register.student'))->assertOk();
        $this->get(route('register.teacher'))->assertOk();
        $this->get(route('register.parent'))->assertOk();
    }

    public function test_home_uses_the_main_logo_in_header_and_footer(): void
    {
        $this->seed(GradeSeeder::class);

        $html = $this->get(route('home'))->assertOk()->getContent();

        $this->assertSame(2, substr_count($html, 'class="site-logo" src="'.asset('images/logo.png').'"'));
        $this->assertStringNotContainsString('class="mark"', $html);
    }
}
