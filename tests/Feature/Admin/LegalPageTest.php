<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\LegalPage;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Quản trị → Chính sách (CMS): tạo / sửa trang, slug cố định. Migration nạp sẵn 2 trang hệ thống (bản mặc định)
 * + 6 trang lấy từ production, nên RefreshDatabase là đã có đủ 8 trang.
 */
class LegalPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    private function user(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }

    private function page(string $slug): LegalPage
    {
        return LegalPage::where('slug', $slug)->firstOrFail();
    }

    public function test_all_eight_policy_pages_are_live_at_their_original_urls(): void
    {
        foreach (['dieu-khoan-su-dung', 'chinh-sach-bao-mat', 'chinh-sach-gia', 'chinh-sach-thanh-toan', 'chinh-sach-giao-nhan-hoan-huy',
            'giai-quyet-khieu-nai', 'dieu-kien-han-che-dich-vu', 'quyen-va-nghia-vu'] as $slug) {
            $this->get('/'.$slug)->assertOk()->assertSee('Các chính sách khác');
        }

        $this->get('/chinh-sach-gia')->assertSee('1. Niêm yết giá')->assertSee('Cập nhật 29/10/2026');
    }

    public function test_system_pages_show_the_default_until_an_admin_writes_one(): void
    {
        config(['site.legal_updated_at' => '29/10/2026']);

        $this->get(route('legal.terms'))->assertOk()->assertSee('Cập nhật 29/10/2026')->assertSee('1. Dịch vụ');
        $this->get(route('legal.privacy'))->assertOk()->assertSee('Cookie phân tích');
    }

    public function test_unknown_paths_still_404(): void
    {
        $this->get('/khong-co-trang-nay')->assertNotFound();
        $this->get('/chinh-sach-gia/them')->assertNotFound();
    }

    public function test_admin_creates_a_page_with_a_slug_from_the_title(): void
    {
        $this->actingAs($this->user('admin'))
            ->post(route('admin.legal.store'), [
                'title' => 'Chính sách đổi trả khoá học',
                'content' => '<h2>1. Điều kiện</h2><p>Trong 7 ngày.</p>',
                'is_active' => '1',
            ])
            ->assertRedirect();

        $page = $this->page('chinh-sach-doi-tra-khoa-hoc');
        $this->assertTrue(AuditLog::where('action', 'legal.created')->exists());

        $this->get('/chinh-sach-doi-tra-khoa-hoc')->assertOk()->assertSee('1. Điều kiện');
        // Tự hiện ở chân trang các trang công khai và trong sitemap.
        $this->get(route('packages.index'))->assertSee($page->url(), false);
        $this->get(route('sitemap'))->assertSee('<loc>'.$page->url().'</loc>', false);
    }

    public function test_slug_never_collides_with_an_existing_page_or_route(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->post(route('admin.legal.store'), ['title' => 'Chính sách giá', 'content' => '<p>Bản 2</p>', 'is_active' => '1']);
        $this->assertNotNull(LegalPage::where('slug', 'chinh-sach-gia-2')->first());

        // "Gói học" trùng route /goi-hoc có sẵn — không được đè.
        $this->actingAs($admin)->post(route('admin.legal.store'), ['title' => 'Gói học', 'content' => '<p>x</p>', 'is_active' => '1']);
        $this->assertNull(LegalPage::where('slug', 'goi-hoc')->first());
        $this->assertNotNull(LegalPage::where('slug', 'goi-hoc-2')->first());
        $this->get(route('packages.index'))->assertOk()->assertDontSee('<p>x</p>', false);
    }

    public function test_editing_never_changes_the_slug(): void
    {
        $page = $this->page('chinh-sach-gia');

        $this->actingAs($this->user('admin'))
            ->put(route('admin.legal.update', $page), [
                'title' => 'Bảng giá mới hoàn toàn',
                'slug' => 'duong-dan-khac',
                'content' => '<p>Giá mới</p>',
                'is_active' => '1',
            ])
            ->assertRedirect(route('admin.legal.edit', $page));

        $page->refresh();
        $this->assertSame('chinh-sach-gia', $page->slug);
        $this->assertSame('Bảng giá mới hoàn toàn', $page->title);
        $this->get('/chinh-sach-gia')->assertOk()->assertSee('Giá mới');
        $this->get('/duong-dan-khac')->assertNotFound();
    }

    public function test_edit_form_shows_the_slug_read_only_without_an_input(): void
    {
        $html = $this->actingAs($this->user('admin'))
            ->get(route('admin.legal.edit', $this->page('chinh-sach-gia')))
            ->assertOk()->getContent();

        $this->assertStringContainsString('readonly', $html);
        $this->assertStringNotContainsString('name="slug"', $html);
    }

    public function test_admin_saves_system_page_content_that_replaces_the_default(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 17));
        $page = $this->page('dieu-khoan-su-dung');

        $this->actingAs($this->user('admin'))->put(route('admin.legal.update', $page), [
            'title' => 'Điều khoản sử dụng',
            'content' => '<h2>1. Điều khoản mới</h2><p><a href="mailto:hotro@hoctoanonline.com">hotro@hoctoanonline.com</a> · <a href="tel:+84912345678">gọi</a></p>',
            'is_active' => '0',
        ]);

        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('1. Điều khoản mới')
            ->assertDontSee('1. Dịch vụ')
            ->assertSee('Cập nhật 17/11/2026')
            ->assertSee('href="mailto:hotro@hoctoanonline.com"', false)
            ->assertSee('href="tel:+84912345678"', false);

        // Trang hệ thống không tắt được dù form gửi is_active=0.
        $this->assertTrue($page->fresh()->is_active);
    }

    public function test_system_page_can_go_back_to_the_default(): void
    {
        $admin = $this->user('admin');
        $page = $this->page('dieu-khoan-su-dung');
        $this->actingAs($admin)->put(route('admin.legal.update', $page), ['title' => 'Điều khoản sử dụng', 'content' => '<p>Bản tạm</p>']);

        $this->actingAs($admin)->put(route('admin.legal.reset', $page))->assertRedirect(route('admin.legal.edit', $page));

        $this->assertNull($page->fresh()->content);
        $this->get(route('legal.terms'))->assertSee('1. Dịch vụ')->assertDontSee('Bản tạm');
    }

    public function test_hidden_page_disappears_from_the_site(): void
    {
        $page = $this->page('quyen-va-nghia-vu');

        $this->actingAs($this->user('admin'))->put(route('admin.legal.update', $page), [
            'title' => $page->title, 'content' => '<p>Nội dung</p>', 'is_active' => '0',
        ]);

        $this->get('/quyen-va-nghia-vu')->assertNotFound();
        $this->get(route('legal.terms'))->assertDontSee('/quyen-va-nghia-vu', false);
    }

    public function test_custom_pages_can_be_deleted_but_system_pages_cannot(): void
    {
        $admin = $this->user('admin');

        $this->actingAs($admin)->delete(route('admin.legal.destroy', $this->page('chinh-sach-gia')))->assertRedirect(route('admin.legal.index'));
        $this->assertNull(LegalPage::where('slug', 'chinh-sach-gia')->first());
        $this->get('/chinh-sach-gia')->assertNotFound();

        $this->actingAs($admin)->delete(route('admin.legal.destroy', $this->page('chinh-sach-bao-mat')))->assertForbidden();
        $this->get(route('legal.privacy'))->assertOk();
    }

    public function test_dangerous_html_is_stripped_when_saving(): void
    {
        $this->actingAs($this->user('admin'))->post(route('admin.legal.store'), [
            'title' => 'Thử', 'is_active' => '1',
            'content' => '<h2>A</h2><script>alert(1)</script><a href="javascript:alert(1)">x</a><p onclick="x()">Nội dung</p>',
        ]);

        $saved = $this->page('thu')->content;
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('javascript:', $saved);
        $this->assertStringNotContainsString('onclick', $saved);
        $this->assertStringContainsString('Nội dung', $saved);
    }

    public function test_only_admins_can_manage_pages(): void
    {
        $page = $this->page('chinh-sach-gia');

        foreach (['student', 'teacher'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('admin.legal.index'))->assertForbidden();
            $this->actingAs($user)->post(route('admin.legal.store'), ['title' => 'Hack', 'content' => '<p>x</p>'])->assertForbidden();
            $this->actingAs($user)->put(route('admin.legal.update', $page), ['title' => 'Hack', 'content' => '<p>x</p>'])->assertForbidden();
            $this->actingAs($user)->delete(route('admin.legal.destroy', $page))->assertForbidden();
        }

        $this->assertSame('Chính sách giá', $page->fresh()->title);
    }

    public function test_privacy_editor_reminds_which_third_parties_are_on(): void
    {
        config(['site.google_analytics_id' => 'G-TEST']);

        $this->actingAs($this->user('admin'))
            ->get(route('admin.legal.edit', $this->page('chinh-sach-bao-mat')))
            ->assertOk()
            ->assertSee('1. Dữ liệu chúng tôi thu thập')
            ->assertSee('Google Analytics / Google Tag Manager');
    }
}
