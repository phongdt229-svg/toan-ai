<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AnalyticsPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_sees_analytics_shortcuts(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        $this->actingAs($admin)->get(route('admin.analytics.index'))
            ->assertOk()
            ->assertSee('analytics.google.com');
    }

    public function test_embeds_only_looker_studio_https_urls(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        config(['site.looker_studio_embed_url' => 'https://lookerstudio.google.com/embed/reporting/abc/page/1']);
        $this->actingAs($admin)->get(route('admin.analytics.index'))
            ->assertSee('<iframe', false)
            ->assertSee('lookerstudio.google.com/embed/reporting/abc', false);

        config(['site.looker_studio_embed_url' => 'https://evil.example.com/embed']);
        $this->actingAs($admin)->get(route('admin.analytics.index'))
            ->assertDontSee('<iframe', false);
    }

    public function test_student_cannot_open_it(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $student = User::factory()->create();
        $student->assignRole('student');

        $this->actingAs($student)->get(route('admin.analytics.index'))->assertForbidden();
    }
}
