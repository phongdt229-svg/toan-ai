<?php

namespace Tests\Feature\Infrastructure;

use App\Models\Payment;
use App\Models\Question;
use App\Models\QuestionAttempt;
use App\Services\Admin\AnalyticsService;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Tests\Feature\Subscriptions\SubscriptionTestCase;

class InfrastructureTest extends SubscriptionTestCase
{
    // --- Bảo mật HTTP ------------------------------------------------------------------------

    public function test_security_headers_are_sent(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'SAMEORIGIN')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');

        $csp = $this->get(route('home'))->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString('https://*.momo.vn', $csp);
    }

    public function test_hsts_only_over_https_and_private_pages_are_not_cached(): void
    {
        $this->get('https://localhost/')->assertHeader('Strict-Transport-Security');

        $this->actingAs($this->makeStudent())->get(route('student.dashboard'))
            ->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private');
    }

    public function test_global_rate_limit_applies_per_user_or_ip(): void
    {
        $this->get(route('home'))->assertHeader('X-RateLimit-Limit', 240);
        $this->getJson(route('api.packages'))->assertHeader('X-RateLimit-Limit', 240);
        $this->actingAs($this->makeStudent())->get(route('student.dashboard'))->assertHeader('X-RateLimit-Limit', 300);
    }

    // --- PWA ---------------------------------------------------------------------------------

    public function test_pwa_manifest_icons_and_service_worker_are_wired(): void
    {
        $manifest = json_decode(File::get(public_path('manifest.webmanifest')), true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame('standalone', $manifest['display']);
        foreach ($manifest['icons'] as $icon) {
            $this->assertFileExists(public_path(ltrim($icon['src'], '/')));
        }
        $this->assertContains('512x512', array_column($manifest['icons'], 'sizes'));

        $sw = File::get(public_path('sw.js'));
        $this->assertStringContainsString('/offline.html', $sw);
        $this->assertStringContainsString("request.method !== 'GET'", $sw);
        $this->assertFileExists(public_path('offline.html'));

        $this->get(route('home'))->assertSee('rel="manifest"', false)->assertSee('name="theme-color"', false);
    }

    // --- Sao lưu -----------------------------------------------------------------------------

    public function test_backup_dumps_gzips_and_prunes_old_files_without_password_on_command_line(): void
    {
        $dir = storage_path('framework/testing/backups');
        File::deleteDirectory($dir);
        File::ensureDirectoryExists($dir);
        config(['backup.path' => $dir, 'backup.keep_days' => 7, 'database.connections.mysql.password' => 'bi-mat']);

        $old = "{$dir}/toan_ai_test-20200101-000000.sql.gz";
        File::put($old, 'cu');
        touch($old, now()->subDays(8)->getTimestamp());

        Process::fake(function (PendingProcess $process) {
            $resultFile = collect($process->command)->first(fn ($arg) => str_starts_with($arg, '--result-file='));
            File::put(substr($resultFile, strlen('--result-file=')), "CREATE TABLE users (id int);\n");

            return Process::result();
        });

        $this->artisan('backup:database')->assertSuccessful();

        Process::assertRan(function (PendingProcess $process) {
            $command = implode(' ', $process->command);

            return str_contains($command, '--single-transaction')
                && ! str_contains($command, 'bi-mat')
                && ($process->environment['MYSQL_PWD'] ?? null) === 'bi-mat';
        });

        $files = File::glob("{$dir}/*.sql.gz");
        $this->assertCount(1, $files);
        $this->assertFileDoesNotExist($old);
        $this->assertSame("CREATE TABLE users (id int);\n", gzdecode(File::get($files[0])));
        $this->assertEmpty(File::glob("{$dir}/*.sql"));

        File::deleteDirectory($dir);
    }

    public function test_backup_failure_returns_error_and_leaves_no_partial_file(): void
    {
        $dir = storage_path('framework/testing/backups-fail');
        File::deleteDirectory($dir);
        config(['backup.path' => $dir]);
        Process::fake(fn () => Process::result(errorOutput: 'Access denied', exitCode: 2));

        $this->artisan('backup:database')->assertFailed();

        $this->assertEmpty(File::glob("{$dir}/*"));
        File::deleteDirectory($dir);
    }

    // --- Analytics ---------------------------------------------------------------------------

    public function test_admin_dashboard_shows_revenue_and_active_students_and_can_refresh_cache(): void
    {
        Cache::flush();
        $admin = $this->makeAdmin();
        $student = $this->makeStudent();
        $sub = $this->service()->createPending($student, $this->package('pro-thang'));
        Payment::create([
            'order_code' => 'TEST1', 'user_id' => $student->id, 'package_id' => $sub->package_id,
            'subscription_id' => $sub->id, 'amount' => 99000, 'status' => Payment::STATUS_PAID, 'paid_at' => now(),
        ]);
        $q = Question::published()->firstOrFail();
        QuestionAttempt::create([
            'user_id' => $student->id, 'question_id' => $q->id, 'topic_id' => $q->topic_id, 'context' => 'practice',
            'difficulty' => $q->difficulty, 'answer' => ['value' => 1], 'is_correct' => true, 'score' => 1, 'attempt_no' => 1,
        ]);

        $this->actingAs($admin)->get(route('admin.dashboard'))
            ->assertOk()
            ->assertSee('99.000₫')
            ->assertSee('Học sinh hoạt động 7 ngày');

        $data = app(AnalyticsService::class)->overview();
        $this->assertSame(1, $data['engagement']['active_7d']);
        $this->assertSame(99000, $data['revenue']['month']);
        $this->assertCount(30, $data['daily']);
        $this->assertSame(99000, collect($data['daily'])->sum('revenue'));

        // Số liệu cache: dữ liệu mới chỉ hiện sau khi làm mới.
        Payment::where('order_code', 'TEST1')->update(['amount' => 199000]);
        $this->assertSame(99000, app(AnalyticsService::class)->overview()['revenue']['month']);

        $this->actingAs($admin)->post(route('admin.dashboard.refresh'))->assertRedirect(route('admin.dashboard'));
        $this->assertSame(199000, app(AnalyticsService::class)->overview()['revenue']['month']);
    }
}
