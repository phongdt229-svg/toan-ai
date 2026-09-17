<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

class UserAndAuditTest extends SubscriptionTestCase
{
    public function test_admin_lists_and_filters_users(): void
    {
        $admin = $this->makeAdmin();
        $this->makeStudent('Trần Học Sinh');
        $parent = $this->makeParent();
        $parent->update(['name' => 'Lê Phụ Huynh']);

        $this->actingAs($admin)->get(route('admin.users.index', ['role' => 'student']))
            ->assertOk()->assertSee('Trần Học Sinh')->assertDontSee('Lê Phụ Huynh');

        $this->actingAs($admin)->get(route('admin.users.index', ['q' => 'Phụ Huynh']))
            ->assertOk()->assertSee('Lê Phụ Huynh')->assertDontSee('Trần Học Sinh');
    }

    public function test_student_detail_shows_package_parents_and_related_logs(): void
    {
        $admin = $this->makeAdmin();
        $parent = $this->makeParent();
        $student = $this->makeStudent('Bé Na');
        $this->link($parent, $student);
        $this->subscribe($student, 'premium-thang', $parent);

        $this->actingAs($admin)->get(route('admin.users.show', $student))
            ->assertOk()
            ->assertSee('Premium 1 tháng')
            ->assertSee($parent->name)
            ->assertSee('Phụ huynh liên kết con');

        $this->actingAs($admin)->get(route('admin.users.show', $parent))
            ->assertOk()->assertSee('Con đã liên kết')->assertSee('Bé Na');
    }

    public function test_suspending_logs_out_everywhere_and_is_audited(): void
    {
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $student->createToken('app');

        $this->actingAs($admin)->post(route('admin.users.suspend', $student), ['reason' => 'Spam AI'])
            ->assertSessionHas('status');

        $this->assertSame(User::STATUS_SUSPENDED, $student->fresh()->status);
        $this->assertSame(0, $student->tokens()->count());
        $log = AuditLog::where('action', 'user.suspended')->firstOrFail();
        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('Spam AI', $log->new_values['reason']);

        // Phiên đang mở bị đẩy ra ở request kế tiếp.
        $this->actingAs($student->fresh())->get(route('student.dashboard'))->assertRedirect(route('login'));

        $this->actingAs($admin)->post(route('admin.users.reactivate', $student))->assertSessionHas('status');
        $this->assertSame(User::STATUS_ACTIVE, $student->fresh()->status);
        $this->assertTrue(AuditLog::where('action', 'user.reactivated')->exists());
    }

    public function test_admin_cannot_suspend_self_or_the_last_active_admin(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post(route('admin.users.suspend', $admin), ['reason' => 'x'])->assertSessionHas('error');
        $this->assertSame(User::STATUS_ACTIVE, $admin->fresh()->status);

        $other = $this->makeAdmin();
        $admin->update(['status' => User::STATUS_SUSPENDED]);
        $this->actingAs($other)->post(route('admin.users.suspend', $admin->fresh()), ['reason' => 'x'])->assertSessionHas('error');

        $admin->update(['status' => User::STATUS_ACTIVE]);
        $this->actingAs($other)->post(route('admin.users.suspend', $admin), ['reason' => 'Nghỉ việc'])->assertSessionHas('status');
    }

    public function test_suspend_requires_a_reason(): void
    {
        $this->actingAs($this->makeAdmin())
            ->post(route('admin.users.suspend', $this->makeStudent()), ['reason' => ''])
            ->assertSessionHasErrors('reason');
    }

    public function test_audit_log_filters_and_exports_csv(): void
    {
        $admin = $this->makeAdmin();
        $this->actingAs($admin);
        $this->service()->grant($admin, $this->makeStudent('HS Một'), $this->package('pro-thang'), 7);
        AuditLog::create(['action' => 'lesson.created', 'user_id' => null, 'new_values' => ['title' => 'Phân số']]);
        DB::table('audit_logs')->where('action', 'lesson.created')->update(['created_at' => now()->subDays(10)]);

        $this->get(route('admin.audit-logs.index', ['action' => 'subscription.granted']))
            ->assertOk()->assertSee('Subscription #')->assertDontSee('Phân số');

        $this->get(route('admin.audit-logs.index', ['from' => now()->subDays(2)->toDateString()]))
            ->assertOk()->assertSee('Subscription #')->assertDontSee('Phân số');

        $this->get(route('admin.audit-logs.index', ['from' => 'không-phải-ngày']))->assertOk();

        $csv = $this->get(route('admin.audit-logs.export', ['action' => 'subscription.granted']));
        $csv->assertOk();
        $body = $csv->streamedContent();
        $this->assertStringContainsString('subscription.granted', $body);
        $this->assertStringNotContainsString('lesson.created', $body);
    }

    public function test_only_admins_reach_user_and_audit_pages(): void
    {
        $teacher = $this->makeTeacher();

        $this->actingAs($teacher)->get(route('admin.users.index'))->assertForbidden();
        $this->actingAs($teacher)->get(route('admin.audit-logs.index'))->assertForbidden();
        $this->actingAs($teacher)->post(route('admin.users.suspend', $this->makeStudent()), ['reason' => 'x'])->assertForbidden();
    }
}
