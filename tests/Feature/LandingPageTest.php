<?php

namespace Tests\Feature;

use Database\Seeders\GradeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LandingPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_landing_page_renders_all_sections(): void
    {
        $this->seed(GradeSeeder::class);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Học Toán thông minh cùng AI')
            ->assertSee('Bắt đầu học miễn phí')
            ->assertSee('Lớp 12')
            ->assertSee('Vui lòng đăng nhập để bắt đầu học.');
    }

    public function test_the_feature_list_keeps_up_with_what_was_actually_shipped(): void
    {
        // Trang chủ lặng lẽ tụt lại sau sản phẩm là chuyện rất dễ xảy ra: tính năng làm xong
        // nhưng người vào xem không biết là có. Test này giữ ba mục mới nhất có mặt ở đó.
        // Mặt còn lại của luật "chỉ quảng cáo thứ có thật" — xem FooterSocialLinksTest.
        $this->get('/')->assertOk()
            ->assertSee('Hỏi đáp')
            ->assertSee('Lộ trình riêng')
            ->assertSee('Cài như ứng dụng');
    }

    public function test_the_install_button_is_hidden_until_the_browser_says_it_is_installable(): void
    {
        // Nút gửi xuống ở trạng thái `hidden`; install-app.js mới bỏ `hidden` khi trình duyệt
        // bắn beforeinstallprompt. Safari iPhone không bắn nên nút không bao giờ hiện ở đó —
        // hiện nút chết thì bấm vào không có gì xảy ra.
        $this->get('/')->assertOk()
            ->assertSee('data-install-app hidden', false)
            ->assertSee('Cài ứng dụng');
    }

    public function test_the_faq_answers_the_questions_a_paying_parent_asks(): void
    {
        $this->get('/')->assertOk()
            ->assertSee('Hỏi thường gặp')
            ->assertSee('Không trả tiền thì dùng được gì?')
            ->assertSee('AI có làm bài hộ con không?');
    }

    public function test_the_faq_structured_data_matches_what_is_on_screen(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        preg_match('~<script type="application/ld\+json">(.*?)</script>~s', $html, $m);
        $this->assertNotEmpty($m, 'Thiếu dữ liệu có cấu trúc FAQ cho Google.');

        $data = json_decode($m[1], true);
        $this->assertSame('FAQPage', $data['@type']);

        // Google phạt trang khai một đằng hiện một nẻo — mọi câu khai báo phải có trên màn hình.
        foreach ($data['mainEntity'] as $entry) {
            $this->assertStringContainsString($entry['name'], $html);
            $this->assertNotEmpty($entry['acceptedAnswer']['text']);
        }
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
}
