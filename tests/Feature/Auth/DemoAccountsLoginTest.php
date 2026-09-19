<?php

namespace Tests\Feature\Auth;

use Database\Seeders\DemoUserSeeder;
use Database\Seeders\GradeSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DemoAccountsLoginTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RolePermissionSeeder::class, GradeSeeder::class, DemoUserSeeder::class]);
    }

    public function test_login_page_lists_demo_accounts_only_in_local(): void
    {
        $this->app['env'] = 'local';

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('Tài khoản demo')
            ->assertSee('student@gmail.com')
            ->assertSee('parent@gmail.com');

        $this->app['env'] = 'production';

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('Tài khoản demo')
            ->assertDontSee('student@gmail.com');
    }

    public function test_demo_password_shown_actually_logs_in(): void
    {
        $this->post(route('login'), ['email' => 'student@gmail.com', 'password' => config('app.demo_password')])
            ->assertRedirect(route('student.dashboard'));

        $this->assertAuthenticated();
    }
}
