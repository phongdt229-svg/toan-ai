<?php

namespace Tests\Feature\Admin;

use App\Models\Role;
use App\Models\SeoPage;
use App\Models\User;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Sửa meta_description các trang tĩnh công khai từ Quản trị → SEO, không cần đụng code. */
class SeoPageManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);

        $this->admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $this->admin->assignRole(Role::ADMIN);
    }

    public function test_only_admin_can_view_or_update_seo_pages(): void
    {
        $student = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $student->assignRole(Role::STUDENT);

        $this->actingAs($student)->get(route('admin.seo.index'))->assertForbidden();
        $this->actingAs($student)->put(route('admin.seo.update'), [
            'route_name' => 'login',
            'meta_description' => 'Chiếm quyền',
        ])->assertForbidden();

        $this->assertDatabaseMissing('seo_pages', ['route_name' => 'login']);
    }

    public function test_admin_lists_the_configured_pages(): void
    {
        $this->actingAs($this->admin)->get(route('admin.seo.index'))
            ->assertOk()
            ->assertSee('Đăng nhập')
            ->assertSee('Quên mật khẩu');
    }

    public function test_saving_an_override_changes_the_rendered_meta_description(): void
    {
        $this->actingAs($this->admin)->put(route('admin.seo.update'), [
            'route_name' => 'login',
            'meta_description' => 'Mô tả mới do admin tự viết ở Quản trị.',
        ])->assertRedirect();

        $this->assertDatabaseHas('seo_pages', [
            'route_name' => 'login',
            'meta_description' => 'Mô tả mới do admin tự viết ở Quản trị.',
        ]);

        // Trang đăng nhập chặn người đã đăng nhập (middleware guest) — đăng xuất trước khi xem.
        auth()->logout();
        $this->get(route('login'))
            ->assertSee('<meta name="description" content="Mô tả mới do admin tự viết ở Quản trị.">', false);
    }

    public function test_clearing_the_override_falls_back_to_the_description_written_in_the_blade_file(): void
    {
        SeoPage::query()->create(['route_name' => 'login', 'meta_description' => 'Mô tả tạm']);

        $this->actingAs($this->admin)->put(route('admin.seo.update'), [
            'route_name' => 'login',
            'meta_description' => '',
        ])->assertRedirect();

        auth()->logout();
        $this->get(route('login'))
            ->assertSee('<meta name="description" content="Đăng nhập TOÁN AI để tiếp tục học Toán lớp 1–12 cùng AI Tutor.">', false);
    }

    public function test_route_name_outside_the_configured_list_is_rejected(): void
    {
        $this->actingAs($this->admin)->put(route('admin.seo.update'), [
            'route_name' => 'admin.dashboard',
            'meta_description' => 'Không được phép',
        ])->assertSessionHasErrors('route_name');

        $this->assertDatabaseMissing('seo_pages', ['route_name' => 'admin.dashboard']);
    }
}
