<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Link mạng xã hội ở footer — chưa khai thì phải ẩn, không dẫn người dùng tới trang không tồn tại. */
class FooterSocialLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_nothing_is_shown_when_no_network_is_configured(): void
    {
        config(['site.social' => ['facebook' => '', 'youtube' => '', 'tiktok' => '', 'x' => '', 'google' => '']]);

        $this->get('/')->assertOk()->assertDontSee('social-links', false);
    }

    public function test_only_the_configured_networks_appear(): void
    {
        config(['site.social' => [
            'facebook' => 'https://facebook.com/toanai',
            'youtube' => '',
            'tiktok' => 'https://tiktok.com/@toanai',
            'x' => '',
            'google' => '',
        ]]);

        $response = $this->get('/')->assertOk();

        $response->assertSee('https://facebook.com/toanai', false)
            ->assertSee('https://tiktok.com/@toanai', false)
            ->assertSee('bi-facebook', false)
            ->assertSee('bi-tiktok', false)
            // Hai mạng chưa khai không được lòi ra biểu tượng chết.
            ->assertDontSee('bi-youtube', false)
            ->assertDontSee('bi-twitter-x', false);
    }

    public function test_links_open_safely_and_are_readable_by_screen_readers(): void
    {
        config(['site.social' => ['facebook' => 'https://facebook.com/toanai', 'youtube' => '', 'tiktok' => '', 'x' => '', 'google' => '']]);

        $this->get('/')->assertOk()
            ->assertSee('target="_blank" rel="noopener nofollow"', false)
            ->assertSee('aria-label="Facebook"', false);
    }

    public function test_moit_badge_is_hidden_until_the_notification_is_approved(): void
    {
        config(['site.moit_url' => '']);
        $this->get('/')->assertOk()->assertDontSee('Đã thông báo Bộ Công Thương');

        // Link không về online.gov.vn (chép nhầm, link mẫu) cũng không được gắn huy hiệu.
        config(['site.moit_url' => 'https://example.com/WebDetails/123']);
        $this->get('/')->assertOk()->assertDontSee('Đã thông báo Bộ Công Thương');
    }

    public function test_moit_badge_links_to_the_registration_page(): void
    {
        config([
            'site.moit_url' => 'http://online.gov.vn/Home/WebDetails/123456',
            'site.moit_logo' => 'khong-co-file-nay.png',
        ]);

        // Chưa có file logo chính thức → link chữ, không vỡ ảnh.
        $this->get('/')->assertOk()
            ->assertSee('href="http://online.gov.vn/Home/WebDetails/123456"', false)
            ->assertSee('Đã thông báo Bộ Công Thương')
            ->assertDontSee('khong-co-file-nay.png', false);
    }

    public function test_the_hero_does_not_advertise_features_that_do_not_exist(): void
    {
        // Trước 23/09 hero có chip "+10 điểm" và "7 ngày liên tiếp" trong khi hệ thống chưa có cả hai.
        // Chuỗi ngày học đã có từ 09/10 (TA-15, StreakService) nên được phép nhắc; điểm thưởng thì VẪN CHƯA có.
        $html = $this->get('/')->assertOk()->getContent();
        $hero = substr($html, 0, strpos($html, 'device__screen') ?: strlen($html));

        $this->assertStringNotContainsString('+10 điểm', $hero);
    }

    public function test_all_five_networks_render_when_configured(): void
    {
        config(['site.social' => [
            'facebook' => 'https://facebook.com/a',
            'youtube' => 'https://youtube.com/@a',
            'tiktok' => 'https://tiktok.com/@a',
            'x' => 'https://x.com/a',
            'google' => 'https://g.page/a',
        ]]);

        $response = $this->get('/')->assertOk();

        foreach (['bi-facebook', 'bi-youtube', 'bi-tiktok', 'bi-twitter-x', 'bi-google'] as $icon) {
            $response->assertSee($icon, false);
        }
    }
}
