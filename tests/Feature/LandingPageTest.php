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

    public function test_pwa_install_button_starts_hidden_and_only_the_homepage_loads_its_script(): void
    {
        $response = $this->get('/');
        $response->assertOk()
            // Ẩn mặc định: JS mới hiện khi trình duyệt thực sự bắn beforeinstallprompt, không hiện
            // nút bấm không ra gì trên trình duyệt không hỗ trợ.
            ->assertSee('id="pwa-install-btn"', false)
            ->assertSee('d-none', false)
            ->assertSee('pwa-install', false);

        // Trang khác không nạp entry riêng này — Vite tách file để không cõng thêm JS vào mọi trang.
        $this->seed(GradeSeeder::class);
        $this->get(route('login'))->assertOk()->assertDontSee('pwa-install', false);
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
