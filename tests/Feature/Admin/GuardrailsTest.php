<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use App\Services\AI\AiUsageGuard;
use App\Support\SocialLink;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Lưới an toàn cho hai lỗi cấu hình dễ lọt lên production: thiếu giá AI, link mạng xã hội giả. */
class GuardrailsTest extends TestCase
{
    use RefreshDatabase;

    public function test_pricing_lookup_matches_dated_model_suffix(): void
    {
        $this->assertTrue(AiUsageGuard::hasPricing('gpt-4o-mini-2024-07-18'));
        $this->assertFalse(AiUsageGuard::hasPricing('gemini-2.0-flash'));
    }

    public function test_ai_usage_page_warns_when_model_has_no_price(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create();
        $admin->assignRole('admin');

        config(['ai.provider' => 'openai', 'ai.openai.api_key' => 'k', 'ai.openai.model' => 'gemini-2.0-flash']);
        $this->actingAs($admin)->get(route('admin.ai-usage.index'))->assertOk()->assertSee('chưa có dòng giá');

        config(['ai.openai.model' => 'gpt-4o-mini']);
        $this->get(route('admin.ai-usage.index'))->assertDontSee('chưa có dòng giá');
    }

    public function test_placeholder_social_links_are_rejected(): void
    {
        foreach (['https://facebook.com/x', 'https://google.com/test', 'https://example.com/page', 'ftp://a.com/b', 'facebook', '', null] as $bad) {
            $this->assertFalse(SocialLink::isUsable($bad), (string) $bad);
        }

        foreach (['https://facebook.com/toanai', 'https://tiktok.com/@toanai', 'https://g.page/toanai'] as $good) {
            $this->assertTrue(SocialLink::isUsable($good), $good);
        }
    }

    public function test_footer_hides_placeholder_links(): void
    {
        config(['site.social' => ['facebook' => 'https://facebook.com/x', 'youtube' => '', 'tiktok' => '', 'x' => '', 'google' => '']]);

        $this->get(route('home'))->assertOk()->assertDontSee('facebook.com/x', false);
    }
}
