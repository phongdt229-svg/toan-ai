<?php

namespace Tests\Feature\Admin;

use App\Models\Grade;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Services\Admin\MaintenanceModeService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Http\MaintenanceModeBypassCookie;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Tests\TestCase;

/**
 * Bật / tắt bảo trì từ trang quản trị.
 *
 * Lưu ý: chế độ bảo trì ghi vào storage/ dùng chung với app đang chạy ở local,
 * nên tearDown LUÔN gọi `artisan up` — test hỏng giữa chừng cũng không để site nằm im.
 */
class MaintenanceModeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
    }

    protected function tearDown(): void
    {
        Artisan::call('up');

        // Sót lại trạng thái bảo trì là mọi test chạy sau đó đều nhận 503, và site local
        // cũng nằm im — nên dọn tới cùng chứ không tin mỗi mã trả về của lệnh.
        if (app()->isDownForMaintenance()) {
            app()->maintenanceMode()->deactivate();
            @unlink(storage_path('framework/maintenance.php'));
        }

        parent::tearDown();
    }

    private function admin(): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    private function student(): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $user->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        return $user;
    }

    // --- Bật ----------------------------------------------------------------------------

    public function test_admin_turns_maintenance_on_and_visitors_see_the_vietnamese_page(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->post(route('admin.maintenance.store'), ['confirm' => '1', 'eta' => '45 phút'])
            ->assertRedirect(route('admin.maintenance.edit'))
            ->assertSessionHas('status')
            ->assertCookie(MaintenanceModeService::BYPASS_COOKIE);

        $this->assertTrue(app()->isDownForMaintenance());

        // Khách vãng lai: 503 + đúng trang bảo trì, kèm thời gian admin vừa nhập.
        $response = $this->get('/');
        $response->assertStatus(503)
            ->assertSee('Hệ thống đang được nâng cấp')
            ->assertSee('45 phút')
            ->assertHeader('Retry-After', 60);
    }

    public function test_enabling_requires_the_confirmation_checkbox(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.maintenance.edit'))
            ->post(route('admin.maintenance.store'), ['eta' => '10 phút'])
            ->assertSessionHasErrors('confirm');

        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_only_admins_can_touch_maintenance_mode(): void
    {
        $student = $this->student();

        $this->actingAs($student)->get(route('admin.maintenance.edit'))->assertForbidden();
        $this->actingAs($student)->post(route('admin.maintenance.store'), ['confirm' => '1'])->assertForbidden();

        $this->assertFalse(app()->isDownForMaintenance());
    }

    public function test_it_records_who_turned_it_on(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->post(route('admin.maintenance.store'), ['confirm' => '1', 'eta' => '20 phút']);

        $status = app(MaintenanceModeService::class)->status();
        $this->assertSame($admin->name, $status['enabled_by']);
        $this->assertSame('20 phút', $status['eta']);
        $this->assertNotNull($status['enabled_at']);
        $this->assertNotNull($status['secret']);

        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.enabled', 'user_id' => $admin->id]);
    }

    // --- Vẫn vào được khi đang bảo trì ----------------------------------------------------

    public function test_the_bypass_cookie_lets_the_admin_keep_working(): void
    {
        $secret = app(MaintenanceModeService::class)->enable($this->admin());

        // Trang chủ cũng nằm trong vùng bảo trì như mọi trang khác.
        $this->get('/')->assertStatus(503)->assertSee('Hệ thống đang được nâng cấp');

        $cookie = MaintenanceModeBypassCookie::create($secret);
        $this->withUnencryptedCookie($cookie->getName(), $cookie->getValue())
            ->get('/')
            ->assertOk()
            // Người đi qua được phải biết mình là ngoại lệ, không thì tưởng bật hụt.
            ->assertSee('Site đang bảo trì.');
    }

    public function test_the_secret_url_hands_out_a_bypass_cookie(): void
    {
        $secret = app(MaintenanceModeService::class)->enable($this->admin());

        $this->get('/'.$secret)
            ->assertRedirect('/')
            ->assertCookie(MaintenanceModeService::BYPASS_COOKIE);
    }

    public function test_login_and_the_maintenance_page_stay_reachable_as_a_way_back_in(): void
    {
        $admin = $this->admin();
        app(MaintenanceModeService::class)->enable($admin);

        // Không có cookie bỏ qua: hai đường cứu hộ này vẫn mở, mọi thứ khác thì không.
        $this->get(route('login'))->assertOk();
        $this->actingAs($admin)->get(route('admin.maintenance.edit'))->assertOk()->assertSee('Đang bảo trì');
        $this->get('/up')->assertOk();

        $this->actingAs($admin)->get(route('admin.dashboard'))->assertStatus(503);
    }

    // --- Tắt ------------------------------------------------------------------------------

    public function test_admin_turns_maintenance_off_again(): void
    {
        $admin = $this->admin();
        app(MaintenanceModeService::class)->enable($admin);

        $this->actingAs($admin)
            ->delete(route('admin.maintenance.destroy'))
            ->assertRedirect(route('admin.maintenance.edit'))
            ->assertSessionHas('status');

        $this->assertFalse(app()->isDownForMaintenance());
        $this->assertNull(app(MaintenanceModeService::class)->status());
        $this->assertDatabaseHas('audit_logs', ['action' => 'maintenance.disabled', 'user_id' => $admin->id]);

        $this->get('/')->assertOk();
    }

    public function test_the_page_shows_the_current_state(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->get(route('admin.maintenance.edit'))
            ->assertOk()
            ->assertSee('Đang phục vụ bình thường')
            ->assertSee('Bật chế độ bảo trì');

        app(MaintenanceModeService::class)->enable($admin, '5 phút');

        $this->actingAs($admin)->get(route('admin.maintenance.edit'))
            ->assertOk()
            ->assertSee('Đang bảo trì')
            ->assertSee('Tắt bảo trì, mở lại site')
            ->assertSee('5 phút');
    }
}
