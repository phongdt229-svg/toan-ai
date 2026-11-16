<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\LegalPage;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Quản trị → Chính sách: admin soạn Điều khoản / Chính sách bảo mật, chưa soạn thì trang dùng bản mặc định. */
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

    public function test_pages_show_the_default_until_an_admin_writes_one(): void
    {
        config(['site.legal_updated_at' => '29/10/2026']);

        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('Cập nhật 29/10/2026')
            ->assertSee('1. Dịch vụ');
        $this->get(route('legal.privacy'))->assertOk()
            ->assertSee('Cookie phân tích');
    }

    public function test_admin_edit_form_is_prefilled_with_the_current_default(): void
    {
        $this->actingAs($this->user('admin'))
            ->get(route('admin.legal.edit', 'privacy'))
            ->assertOk()
            ->assertSee('data-rich-editor', false)
            ->assertSee('1. Dữ liệu chúng tôi thu thập')
            ->assertSee('Dịch vụ bên thứ ba đang bật');
    }

    public function test_admin_saves_content_that_replaces_the_page_and_its_date(): void
    {
        $this->travelTo(now()->setDate(2026, 11, 16));
        $admin = $this->user('admin');

        $this->actingAs($admin)
            ->put(route('admin.legal.update', 'terms'), ['content' => '<h2>1. Điều khoản mới</h2><p>Liên hệ <a href="mailto:hotro@hoctoanonline.com">hotro@hoctoanonline.com</a> hoặc <a href="tel:+84912345678">gọi</a>.</p>'])
            ->assertRedirect(route('admin.legal.edit', 'terms'));

        $this->get(route('legal.terms'))->assertOk()
            ->assertSee('1. Điều khoản mới')
            ->assertDontSee('1. Dịch vụ')
            ->assertSee('Cập nhật 16/11/2026')
            // Mục Liên hệ cần link email/điện thoại — bộ lọc riêng của trang này phải giữ lại.
            ->assertSee('href="mailto:hotro@hoctoanonline.com"', false)
            ->assertSee('href="tel:+84912345678"', false);

        // Trang còn lại không bị ảnh hưởng.
        $this->get(route('legal.privacy'))->assertSee('1. Dữ liệu chúng tôi thu thập');
        $this->assertTrue(AuditLog::where('action', 'legal.updated')->exists());
    }

    public function test_dangerous_html_is_stripped_when_saving(): void
    {
        $this->actingAs($this->user('admin'))->put(route('admin.legal.update', 'privacy'), [
            'content' => '<h2>Chính sách</h2><script>alert(1)</script><a href="javascript:alert(1)">x</a><p onclick="x()">Nội dung</p>',
        ]);

        $saved = LegalPage::where('slug', 'privacy')->value('content');
        $this->assertStringNotContainsString('<script', $saved);
        $this->assertStringNotContainsString('javascript:', $saved);
        $this->assertStringNotContainsString('onclick', $saved);
        $this->assertStringContainsString('Nội dung', $saved);
    }

    public function test_restoring_the_default_drops_the_custom_text(): void
    {
        $admin = $this->user('admin');
        $this->actingAs($admin)->put(route('admin.legal.update', 'terms'), ['content' => '<p>Bản tạm</p>']);

        $this->actingAs($admin)->delete(route('admin.legal.destroy', 'terms'))
            ->assertRedirect(route('admin.legal.edit', 'terms'));

        $this->assertDatabaseMissing('legal_pages', ['slug' => 'terms']);
        $this->get(route('legal.terms'))->assertSee('1. Dịch vụ')->assertDontSee('Bản tạm');
    }

    public function test_saving_empty_content_falls_back_to_the_default(): void
    {
        $this->actingAs($this->user('admin'))->put(route('admin.legal.update', 'terms'), ['content' => '<p> </p><script>x</script>']);

        $this->assertDatabaseMissing('legal_pages', ['slug' => 'terms']);
        $this->get(route('legal.terms'))->assertSee('1. Dịch vụ');
    }

    public function test_only_admins_can_edit(): void
    {
        foreach (['student', 'teacher'] as $role) {
            $user = $this->user($role);

            $this->actingAs($user)->get(route('admin.legal.index'))->assertForbidden();
            $this->actingAs($user)->put(route('admin.legal.update', 'terms'), ['content' => '<p>Hack</p>'])->assertForbidden();
        }

        $this->assertDatabaseCount('legal_pages', 0);
    }

    public function test_unknown_page_is_not_found(): void
    {
        $this->actingAs($this->user('admin'))->get('/quan-tri/chinh-sach/khac/sua')->assertNotFound();
    }

    public function test_admin_menu_links_to_the_policy_editor(): void
    {
        $this->actingAs($this->user('admin'))->get(route('admin.legal.index'))->assertOk()
            ->assertSee('Điều khoản sử dụng')
            ->assertSee('Chính sách bảo mật')
            ->assertSee(route('admin.legal.edit', 'terms'), false);
    }
}
