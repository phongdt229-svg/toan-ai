<?php

namespace Tests\Feature\Auth;

use App\Models\AuditLog;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class DataExportTest extends TestCase
{
    use RefreshDatabase;

    private function student(string $name = 'Học Sinh A'): User
    {
        $this->seed(RolePermissionSeeder::class);
        $user = User::factory()->create(['name' => $name, 'password' => 'secret-Pass1']);
        $user->assignRole('student');

        return $user;
    }

    public function test_download_requires_the_current_password(): void
    {
        $user = $this->student();

        $this->actingAs($user)->post(route('account.export'), ['current_password' => 'sai'])
            ->assertSessionHasErrors('current_password');
        $this->assertSame(0, AuditLog::where('action', 'account.data_exported')->count());
    }

    public function test_export_contains_own_data_and_never_secrets_or_other_users(): void
    {
        $user = $this->student('Học Sinh A');
        $other = $this->student('Người Khác');

        foreach ([$user, $other] as $u) {
            $c = DB::table('ai_conversations')->insertGetId([
                'user_id' => $u->id, 'mode' => 'chat', 'title' => 'hội thoại của '.$u->name,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('ai_messages')->insert([
                'ai_conversation_id' => $c, 'role' => 'user', 'content' => 'tin nhắn '.$u->name,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $user->forceFill(['two_factor_secret' => encrypt('SECRETSECRET')])->save();

        $response = $this->actingAs($user)->post(route('account.export'), ['current_password' => 'secret-Pass1']);

        $response->assertOk()->assertHeader('content-disposition');
        $body = $response->streamedContent();
        $data = json_decode($body, true);

        $this->assertSame($user->email, $data['account']['email']);
        $this->assertStringContainsString('tin nhắn Học Sinh A', $body);
        $this->assertStringNotContainsString('Người Khác', $body);
        $this->assertStringNotContainsString('SECRETSECRET', $body);
        $this->assertStringNotContainsString($user->password, $body);
        $this->assertSame(1, AuditLog::where('action', 'account.data_exported')->count());
    }

    public function test_guest_cannot_export(): void
    {
        $this->post(route('account.export'), ['current_password' => 'x'])->assertRedirect(route('login'));
    }
}
