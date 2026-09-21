<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\Grade;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\AccountDeletionRequested;
use App\Services\Auth\AccountDeletionService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class AccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
        Notification::fake();
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

    private function admin(): User
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::ADMIN);

        return $user;
    }

    // --- Người dùng tự yêu cầu xoá ------------------------------------------------------

    public function test_student_can_request_deletion_and_is_logged_out(): void
    {
        $user = $this->student();

        $this->actingAs($user)
            ->delete(route('account.destroy'), ['password' => 'password', 'reason' => 'Không dùng nữa'])
            ->assertRedirect(route('home'))
            ->assertSessionHas('status');

        $this->assertGuest();
        $this->assertSoftDeleted('users', ['id' => $user->id]);

        Notification::assertSentTo($user, AccountDeletionRequested::class);
        $this->assertDatabaseHas('audit_logs', [
            'action' => 'account.deletion_requested',
            'auditable_id' => $user->id,
        ]);
    }

    public function test_reason_is_recorded_for_support_to_read(): void
    {
        $user = $this->student();

        $this->actingAs($user)->delete(route('account.destroy'), [
            'password' => 'password',
            'reason' => 'Chuyển sang lớp khác',
        ]);

        $log = AuditLog::where('action', 'account.deletion_requested')->firstOrFail();
        $this->assertSame('Chuyển sang lớp khác', $log->new_values['reason']);
    }

    public function test_wrong_password_does_not_delete_anything(): void
    {
        $user = $this->student();

        $this->actingAs($user)
            ->from(route('student.settings'))
            ->delete(route('account.destroy'), ['password' => 'sai-mat-khau'])
            ->assertSessionHasErrors('password');

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
        Notification::assertNothingSent();
    }

    public function test_last_active_admin_cannot_delete_their_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)
            ->from(route('admin.settings'))
            ->delete(route('account.destroy'), ['password' => 'password'])
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted('users', ['id' => $admin->id]);

        // Có quản trị viên thứ hai thì mới được rời đi.
        $this->admin();
        $this->actingAs($admin->fresh())
            ->delete(route('account.destroy'), ['password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertSoftDeleted('users', ['id' => $admin->id]);
    }

    public function test_deleted_account_cannot_log_in_again(): void
    {
        $user = $this->student();
        app(AccountDeletionService::class)->request($user);

        $this->post(route('login'), ['email' => $user->email, 'password' => 'password'])
            ->assertSessionHasErrors();

        $this->assertGuest();
    }

    // --- Quản trị: khôi phục trong thời gian chờ -----------------------------------------

    public function test_admin_sees_pending_deletions_and_can_restore(): void
    {
        $admin = $this->admin();
        $user = $this->student();
        app(AccountDeletionService::class)->request($user);

        // Danh sách mặc định không còn thấy tài khoản này nữa.
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertDontSee($user->email);

        $this->actingAs($admin)
            ->get(route('admin.users.index', ['status' => 'deleted']))
            ->assertOk()
            ->assertSee($user->email)
            ->assertSee('Khôi phục');

        $this->actingAs($admin)
            ->from(route('admin.users.index', ['status' => 'deleted']))
            ->post(route('admin.users.restore', $user->id))
            ->assertSessionHas('status');

        $this->assertNotSoftDeleted('users', ['id' => $user->id]);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.restored', 'auditable_id' => $user->id]);
    }

    public function test_only_admin_can_restore(): void
    {
        $user = $this->student();
        $other = $this->student();
        app(AccountDeletionService::class)->request($user);

        $this->actingAs($other)->post(route('admin.users.restore', $user->id))->assertForbidden();
        $this->assertSoftDeleted('users', ['id' => $user->id]);
    }

    // --- Hết hạn giữ: ẩn danh -------------------------------------------------------------

    public function test_purge_only_touches_accounts_past_the_grace_period(): void
    {
        $recent = $this->student();
        $old = $this->student();

        app(AccountDeletionService::class)->request($recent);
        app(AccountDeletionService::class)->request($old);
        User::withTrashed()->whereKey($old->id)
            ->update(['deleted_at' => now()->subDays(AccountDeletionService::GRACE_DAYS + 1)]);

        $this->assertSame(1, app(AccountDeletionService::class)->purgeDue());

        $this->assertSame($recent->email, User::withTrashed()->findOrFail($recent->id)->email);
        $this->assertSame("deleted-{$old->id}@deleted.invalid", User::withTrashed()->findOrFail($old->id)->email);
    }

    public function test_purge_wipes_personal_data_but_keeps_the_row(): void
    {
        $user = $this->student();
        $conversation = DB::table('ai_conversations')->insertGetId([
            'user_id' => $user->id,
            'title' => 'Hỏi bài',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('ai_messages')->insert([
            'ai_conversation_id' => $conversation,
            'role' => 'user',
            'content' => 'Giải giúp em bài này',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('support_tickets')->insert([
            'code' => SupportTicket::generateCode(),
            'user_id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'subject' => 'Lỗi đăng nhập',
            'message' => 'Không vào được',
            'type' => SupportTicket::TYPE_SUPPORT,
            'status' => SupportTicket::STATUS_NEW,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(AccountDeletionService::class)->request($user);
        User::withTrashed()->whereKey($user->id)
            ->update(['deleted_at' => now()->subDays(AccountDeletionService::GRACE_DAYS + 1)]);

        $this->artisan('accounts:purge')->assertSuccessful();

        $purged = User::withTrashed()->findOrFail($user->id);
        $this->assertSame('Người dùng đã xoá', $purged->name);
        $this->assertNull($purged->phone);
        $this->assertDatabaseCount('ai_conversations', 0);
        $this->assertDatabaseCount('ai_messages', 0);
        $this->assertDatabaseMissing('student_profiles', ['user_id' => $user->id]);

        // Yêu cầu hỗ trợ còn để đối soát, nhưng không còn dữ liệu cá nhân.
        $this->assertDatabaseHas('support_tickets', [
            'user_id' => $user->id,
            'name' => 'Người dùng đã xoá',
            'email' => 'deleted@deleted.invalid',
        ]);
    }

    public function test_purge_does_not_run_twice_on_the_same_account(): void
    {
        $user = $this->student();
        app(AccountDeletionService::class)->request($user);
        User::withTrashed()->whereKey($user->id)
            ->update(['deleted_at' => now()->subDays(AccountDeletionService::GRACE_DAYS + 1)]);

        $this->assertSame(1, app(AccountDeletionService::class)->purgeDue());
        $this->assertSame(0, app(AccountDeletionService::class)->purgeDue());
    }
}
