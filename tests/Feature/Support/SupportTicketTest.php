<?php

namespace Tests\Feature\Support;

use App\Models\AuditLog;
use App\Models\Role;
use App\Models\SupportTicket;
use App\Models\User;
use App\Notifications\SupportTicketReceived;
use App\Support\MathCaptcha;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SupportTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class]);
        Notification::fake();
        RateLimiter::clear('support|khach@example.com|127.0.0.1');
    }

    /** Lấy câu hỏi captcha đang nằm trong session rồi tự tính đáp án. */
    private function solveCaptcha(): int
    {
        $this->get(route('support.create'))->assertOk();

        $stored = session('captcha.math');
        $this->assertIsArray($stored, 'Trang hỗ trợ phải sinh captcha.');

        return (int) $stored['answer'];
    }

    /** @param  array<string, mixed>  $overrides */
    private function payload(array $overrides = []): array
    {
        return array_merge([
            'type' => SupportTicket::TYPE_CONTENT_ERROR,
            'name' => 'Nguyễn Khách',
            'email' => 'khach@example.com',
            'subject' => 'Câu hỏi phân số có đáp án sai',
            'message' => 'Câu hỏi về cộng phân số ở lớp 6 đang chấm đáp án 5/6 là sai, phải là 5/12 mới đúng.',
            'context_url' => 'http://localhost/hoc-sinh/bai-hoc/phan-so',
            'captcha' => $this->solveCaptcha(),
        ], $overrides);
    }

    // --- Gửi yêu cầu -------------------------------------------------------------------

    public function test_form_shows_captcha_question_and_types(): void
    {
        $this->get(route('support.create', ['loai' => 'content_error']))
            ->assertOk()
            ->assertSee('không phải robot')
            ->assertSee('Báo lỗi nội dung')
            ->assertSee('Yêu cầu hỗ trợ');
    }

    public function test_guest_can_send_a_ticket_and_support_inbox_is_notified(): void
    {
        $response = $this->post(route('support.store'), $this->payload());

        $ticket = SupportTicket::firstOrFail();
        $response->assertRedirect(route('support.create'))
            ->assertSessionHas('status', fn ($message) => str_contains($message, $ticket->code));

        $this->assertSame(SupportTicket::TYPE_CONTENT_ERROR, $ticket->type);
        $this->assertSame(SupportTicket::STATUS_NEW, $ticket->status);
        $this->assertNull($ticket->user_id);
        $this->assertSame('127.0.0.1', $ticket->ip_address);
        $this->assertStringStartsWith('HT-', $ticket->code);

        Notification::assertSentOnDemand(SupportTicketReceived::class, function ($notification, $channels, $notifiable) use ($ticket) {
            return $notifiable->routes['mail'] === config('site.email')
                && $notification->ticket->is($ticket);
        });
    }

    public function test_logged_in_user_does_not_retype_name_and_email(): void
    {
        $user = User::factory()->create(['status' => User::STATUS_ACTIVE, 'name' => 'Lê Học Sinh']);
        $user->assignRole(Role::STUDENT);

        $this->actingAs($user)
            ->post(route('support.store'), $this->payload(['name' => 'Giả mạo', 'email' => 'gia-mao@example.com']))
            ->assertSessionHasNoErrors();

        $ticket = SupportTicket::firstOrFail();
        $this->assertSame($user->id, $ticket->user_id);
        $this->assertSame($user->email, $ticket->email);
        $this->assertSame('Lê Học Sinh', $ticket->name);
    }

    // --- Captcha & chống spam -----------------------------------------------------------

    public function test_wrong_captcha_is_rejected(): void
    {
        $answer = $this->solveCaptcha();

        $this->post(route('support.store'), $this->payload(['captcha' => $answer + 1]))
            ->assertSessionHasErrors('captcha');

        $this->assertSame(0, SupportTicket::count());
        Notification::assertNothingSent();
    }

    public function test_captcha_cannot_be_reused(): void
    {
        $payload = $this->payload();

        $this->post(route('support.store'), $payload)->assertSessionHasNoErrors();
        $this->post(route('support.store'), $payload)->assertSessionHasErrors('captcha');

        $this->assertSame(1, SupportTicket::count());
    }

    public function test_honeypot_field_blocks_bots(): void
    {
        $this->post(route('support.store'), $this->payload(['website' => 'http://spam.example']))
            ->assertSessionHasErrors('website');

        $this->assertSame(0, SupportTicket::count());
    }

    public function test_broken_encoding_does_not_crash_the_request(): void
    {
        // "ài há»c" kiểu này là tiếng Việt bị sai mã — bot hay gửi.
        $this->post(route('support.store'), $this->payload([
            'subject' => "Bài hõc có lõi",
            'message' => "Nõi dung báo lõi dài hõn hai mõõi ký tõ de kiem tra.",
        ]))->assertSessionHasNoErrors();

        $ticket = SupportTicket::firstOrFail();
        $this->assertTrue(mb_check_encoding($ticket->subject, 'UTF-8'));
        $this->assertTrue(mb_check_encoding($ticket->message, 'UTF-8'));
    }

    public function test_short_message_is_rejected(): void
    {
        $this->post(route('support.store'), $this->payload(['message' => 'Sai rồi']))
            ->assertSessionHasErrors('message');
    }

    public function test_sending_too_many_tickets_is_throttled(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->post(route('support.store'), $this->payload())->assertSessionHasNoErrors();
        }

        $this->post(route('support.store'), $this->payload())->assertSessionHasErrors('message');
        $this->assertSame(5, SupportTicket::count());
    }

    public function test_captcha_helper_expires(): void
    {
        MathCaptcha::question();
        $this->travel(16)->minutes();

        $this->assertFalse(MathCaptcha::check(session('captcha.math')['answer'] ?? 0));
    }

    // --- Quản trị -----------------------------------------------------------------------

    public function test_admin_lists_filters_and_updates_tickets(): void
    {
        $this->post(route('support.store'), $this->payload());
        $ticket = SupportTicket::firstOrFail();

        $admin = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $admin->assignRole(Role::ADMIN);

        $this->actingAs($admin)->get(route('admin.support.index'))
            ->assertOk()->assertSee($ticket->code)->assertSee('Báo lỗi nội dung');

        $this->actingAs($admin)->get(route('admin.support.index', ['type' => 'payment']))
            ->assertOk()->assertDontSee($ticket->code);

        $this->actingAs($admin)->get(route('admin.support.show', $ticket))
            ->assertOk()->assertSee('đáp án 5/6 là sai', false);

        $this->actingAs($admin)->put(route('admin.support.update', $ticket), [
            'status' => SupportTicket::STATUS_RESOLVED,
            'admin_note' => 'Đã sửa đáp án câu hỏi.',
        ])->assertSessionHas('status');

        $ticket->refresh();
        $this->assertSame(SupportTicket::STATUS_RESOLVED, $ticket->status);
        $this->assertSame($admin->id, $ticket->handled_by);
        $this->assertNotNull($ticket->handled_at);
        $this->assertTrue(AuditLog::where('action', 'support.updated')->exists());
    }

    public function test_only_admin_reaches_support_admin_pages(): void
    {
        $teacher = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $teacher->assignRole(Role::TEACHER);

        $this->actingAs($teacher)->get(route('admin.support.index'))->assertForbidden();
    }
}
