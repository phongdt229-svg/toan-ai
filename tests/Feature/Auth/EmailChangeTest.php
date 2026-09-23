<?php

namespace Tests\Feature\Auth;

use App\Models\Grade;
use App\Models\Role;
use App\Models\StudentProfile;
use App\Models\User;
use App\Notifications\ConfirmNewEmail;
use App\Notifications\EmailChangeRequested;
use App\Notifications\VerifyEmailLink;
use App\Services\Auth\EmailChangeService;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/**
 * Đổi email tài khoản (đợt 23/09).
 *
 * Điều phải giữ: email KHÔNG đổi cho tới khi có người bấm được link gửi tới địa chỉ mới.
 */
class EmailChangeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
        Notification::fake();
    }

    private function student(string $email = 'cu@example.com'): User
    {
        $user = User::factory()->create(['email' => $email, 'status' => User::STATUS_ACTIVE]);
        $user->assignRole(Role::STUDENT);
        StudentProfile::create([
            'user_id' => $user->id,
            'grade_id' => Grade::where('level', 6)->value('id'),
            'link_code' => StudentProfile::generateLinkCode(),
        ]);

        return $user;
    }

    private function confirmUrl(User $user, string $newEmail): string
    {
        return URL::temporarySignedRoute('email-change.confirm', now()->addHour(), [
            'id' => $user->id,
            'hash' => sha1($newEmail),
        ]);
    }

    // --- Yêu cầu đổi -----------------------------------------------------------------------

    public function test_requesting_does_not_change_the_email_yet(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('email-change.store'), [
            'email' => 'moi@example.com',
            'current_password' => 'password',
        ])->assertSessionHas('status');

        $user->refresh();
        // Chưa ai bấm link thì địa chỉ đăng nhập phải giữ nguyên.
        $this->assertSame('cu@example.com', $user->email);
        $this->assertSame('moi@example.com', $user->pending_email);
    }

    public function test_the_confirmation_goes_to_the_new_address_and_a_warning_to_the_old_one(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('email-change.store'), [
            'email' => 'moi@example.com',
            'current_password' => 'password',
        ]);

        // Thư xác nhận phải tới ĐỊA CHỈ MỚI — gửi về địa chỉ cũ là cả luồng mất ý nghĩa.
        Notification::assertSentOnDemand(ConfirmNewEmail::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'moi@example.com');

        // Địa chỉ cũ phải được cảnh báo ngay, kèm link huỷ.
        Notification::assertSentTo($user, EmailChangeRequested::class);
    }

    public function test_the_wrong_password_stops_everything(): void
    {
        $user = $this->student();

        $this->actingAs($user)->from(route('student.settings'))->post(route('email-change.store'), [
            'email' => 'moi@example.com',
            'current_password' => 'sai-mat-khau',
        ])->assertSessionHasErrors('current_password');

        $this->assertNull($user->refresh()->pending_email);
        Notification::assertNothingSent();
    }

    public function test_an_address_someone_else_uses_is_refused(): void
    {
        $this->student('daco@example.com');
        $user = $this->student();

        $this->actingAs($user)->from(route('student.settings'))->post(route('email-change.store'), [
            'email' => 'daco@example.com',
            'current_password' => 'password',
        ])->assertSessionHasErrors('email');

        $this->assertNull($user->refresh()->pending_email);
    }

    // --- Xác nhận --------------------------------------------------------------------------

    public function test_opening_the_link_completes_the_change(): void
    {
        $user = $this->student();
        app(EmailChangeService::class)->request($user, 'moi@example.com');

        $this->actingAs($user->refresh())->get($this->confirmUrl($user, 'moi@example.com'))
            ->assertRedirect(route('student.settings'))
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('moi@example.com', $user->email);
        $this->assertNull($user->pending_email);
        // Bấm được link nghĩa là địa chỉ có thật — không bắt xác thực thêm lần nữa.
        $this->assertNotNull($user->email_verified_at);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email.changed']);
    }

    public function test_a_link_for_a_different_address_changes_nothing(): void
    {
        $user = $this->student();
        app(EmailChangeService::class)->request($user, 'moi@example.com');

        // Chữ ký hợp lệ nhưng hash không khớp pending_email → chặn ở service.
        $this->actingAs($user->refresh())->get($this->confirmUrl($user, 'ke-gian@example.com'))
            ->assertSessionHas('error');

        $this->assertSame('cu@example.com', $user->refresh()->email);
    }

    public function test_a_link_with_a_broken_signature_is_rejected_outright(): void
    {
        $user = $this->student();
        app(EmailChangeService::class)->request($user, 'moi@example.com');

        // Sửa tay một ký tự trong chữ ký → middleware `signed` chặn trước khi vào controller.
        $url = $this->confirmUrl($user, 'moi@example.com');
        $this->get(substr($url, 0, -3).'000')->assertForbidden();

        $this->assertSame('cu@example.com', $user->refresh()->email);
    }

    public function test_someone_else_taking_the_address_while_we_waited_is_handled(): void
    {
        $user = $this->student();
        app(EmailChangeService::class)->request($user, 'moi@example.com');

        // Giữa hai bước, người khác đăng ký đúng địa chỉ đó.
        $this->student('moi@example.com');

        $this->actingAs($user->refresh())->get($this->confirmUrl($user, 'moi@example.com'))
            ->assertSessionHas('error');

        $user->refresh();
        $this->assertSame('cu@example.com', $user->email);
        $this->assertNull($user->pending_email);
    }

    // --- Huỷ -------------------------------------------------------------------------------

    public function test_the_old_address_can_cancel_without_logging_in(): void
    {
        $user = $this->student();
        app(EmailChangeService::class)->request($user, 'ke-gian@example.com');

        $url = URL::temporarySignedRoute('email-change.cancel', now()->addDay(), [
            'id' => $user->id, 'hash' => sha1('ke-gian@example.com'),
        ]);

        // Không đăng nhập: người bị chiếm tài khoản có thể đã không vào được nữa.
        $this->get($url)->assertRedirect(route('login'))->assertSessionHas('status');

        $user->refresh();
        $this->assertNull($user->pending_email);
        $this->assertSame('cu@example.com', $user->email);
    }

    public function test_a_user_can_drop_their_own_pending_request(): void
    {
        $user = $this->student();
        app(EmailChangeService::class)->request($user, 'moi@example.com');

        $this->actingAs($user->refresh())->delete(route('email-change.destroy'))->assertSessionHas('status');

        $this->assertNull($user->refresh()->pending_email);
    }

    // --- Quản trị đổi hộ -------------------------------------------------------------------

    public function test_an_admin_can_change_it_directly_for_someone_locked_out(): void
    {
        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole(Role::ADMIN);
        $user = $this->student();

        $this->actingAs($admin)->post(route('admin.users.email', $user), ['email' => 'sua@example.com'])
            ->assertSessionHas('status');

        $user->refresh();
        $this->assertSame('sua@example.com', $user->email);
        // Quản trị đổi hộ thì KHÔNG coi là đã xác thực — người dùng phải tự mở hộp thư mới.
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmailLink::class);
        $this->assertDatabaseHas('audit_logs', ['action' => 'email.changed_by_admin']);
    }

    public function test_a_student_cannot_change_someone_elses_email(): void
    {
        $user = $this->student();
        $other = $this->student('nguoikhac@example.com');

        $this->actingAs($user)->post(route('admin.users.email', $other), ['email' => 'chiem@example.com'])
            ->assertForbidden();

        $this->assertSame('nguoikhac@example.com', $other->refresh()->email);
    }

    public function test_the_settings_page_shows_a_pending_change(): void
    {
        $user = $this->student();
        app(EmailChangeService::class)->request($user, 'moi@example.com');

        $this->actingAs($user->refresh())->get(route('student.settings'))
            ->assertOk()
            ->assertSee('moi@example.com')
            ->assertSee('Đang chờ xác nhận');
    }
}
