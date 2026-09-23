<?php

namespace Tests\Feature;

use App\Http\Controllers\Web\CookieConsentController as Consent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Đồng ý cookie (§10, đợt 23/09).
 *
 * Điều quan trọng nhất phải giữ: chưa đồng ý thì KHÔNG có một dòng script đo lường nào
 * được gửi xuống trình duyệt. Nạp rồi mới tắt là muộn — cookie của Google đã đặt xong.
 */
class CookieConsentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['site.google_analytics_id' => 'G-TEST12345', 'site.google_tag_manager_id' => 'GTM-TEST123']);
    }

    public function test_no_tracking_script_is_sent_before_a_choice_is_made(): void
    {
        $this->get('/')->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('G-TEST12345', false)
            ->assertSee('Cookie đo lường');
    }

    public function test_accepting_turns_tracking_on(): void
    {
        $this->post(route('cookie.store'), ['choice' => Consent::ACCEPTED])
            ->assertRedirect()
            ->assertCookie(Consent::COOKIE, Consent::ACCEPTED);

        $this->withCookie(Consent::COOKIE, Consent::ACCEPTED)->get('/')
            ->assertOk()
            ->assertSee('G-TEST12345', false)
            // GTM ghép URL lúc chạy nên HTML chỉ có id trong lời gọi, không có chuỗi gtm.js?id=...
            ->assertSee('GTM-TEST123', false)
            // Đã chọn rồi thì thôi hỏi nữa.
            ->assertDontSee('Cookie đo lường');
    }

    public function test_refusing_keeps_tracking_off_and_stops_asking(): void
    {
        $this->post(route('cookie.store'), ['choice' => Consent::REJECTED])
            ->assertRedirect()
            ->assertCookie(Consent::COOKIE, Consent::REJECTED);

        $this->withCookie(Consent::COOKIE, Consent::REJECTED)->get('/')
            ->assertOk()
            ->assertDontSee('googletagmanager.com', false)
            ->assertDontSee('Cookie đo lường');
    }

    public function test_an_invalid_choice_is_rejected(): void
    {
        $this->from('/')->post(route('cookie.store'), ['choice' => 'maybe'])
            ->assertSessionHasErrors('choice');
    }

    public function test_no_consent_is_asked_when_there_is_nothing_to_track(): void
    {
        // Chưa cấu hình GA/GTM thì không có gì để xin phép — chỉ thông báo, không hỏi đồng ý.
        config(['site.google_analytics_id' => '', 'site.google_tag_manager_id' => '']);

        $this->get('/')->assertOk()->assertDontSee('Cookie đo lường');
    }

    public function test_the_footer_link_clears_the_choice_so_the_banner_returns(): void
    {
        $this->withCookie(Consent::COOKIE, Consent::ACCEPTED)->get('/')
            ->assertOk()
            ->assertSee('Cài đặt cookie');

        $this->withCookie(Consent::COOKIE, Consent::ACCEPTED)
            ->delete(route('cookie.destroy'))
            ->assertRedirect()
            ->assertCookieExpired(Consent::COOKIE);
    }

    // --- Thông báo cookie khi chưa bật đo lường -------------------------------------------

    public function test_a_plain_notice_shows_when_there_is_nothing_to_consent_to(): void
    {
        config(['site.google_analytics_id' => '', 'site.google_tag_manager_id' => '']);

        $this->get('/')->assertOk()
            ->assertSee('Trang này dùng cookie')
            ->assertSee('Đã hiểu')
            // Không có gì để xin phép thì đừng hỏi đồng ý.
            ->assertDontSee('Cookie đo lường');
    }

    public function test_acknowledging_the_notice_hides_it(): void
    {
        config(['site.google_analytics_id' => '', 'site.google_tag_manager_id' => '']);

        $this->post(route('cookie.store'), ['choice' => Consent::SEEN])
            ->assertRedirect()
            ->assertCookie(Consent::NOTICE_COOKIE, '1');

        $this->withCookie(Consent::NOTICE_COOKIE, '1')->get('/')
            ->assertOk()
            ->assertDontSee('Trang này dùng cookie');
    }

    public function test_acknowledging_the_notice_is_not_consent_to_tracking(): void
    {
        // Bấm "Đã hiểu" lúc chưa có GA, sau đó production bật GA lên:
        // người dùng VẪN phải được hỏi, vì họ chưa từng đồng ý điều gì.
        $this->withCookie(Consent::NOTICE_COOKIE, '1')->get('/')
            ->assertOk()
            ->assertSee('Cookie đo lường')
            ->assertDontSee('googletagmanager.com', false);
    }

    public function test_the_privacy_policy_explains_the_choice(): void
    {
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Chỉ cookie cần thiết')
            ->assertSee('Cài đặt cookie');
    }
}
