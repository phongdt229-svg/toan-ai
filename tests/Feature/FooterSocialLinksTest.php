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
