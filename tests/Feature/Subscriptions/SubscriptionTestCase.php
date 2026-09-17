<?php

namespace Tests\Feature\Subscriptions;

use App\Models\Package;
use App\Models\Role;
use App\Models\Subscription;
use App\Models\User;
use App\Services\SubscriptionService;
use Database\Seeders\PackageSeeder;
use Tests\Feature\Parents\ParentTestCase;

abstract class SubscriptionTestCase extends ParentTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config(['ai.provider' => 'fake']);
        $this->seed(PackageSeeder::class);
    }

    protected function package(string $slug): Package
    {
        return Package::where('slug', $slug)->firstOrFail();
    }

    /** Cấp gói có hiệu lực ngay (qua đúng luồng pending → activate). */
    protected function subscribe(User $student, string $slug, ?User $payer = null): Subscription
    {
        $service = app(SubscriptionService::class);
        $sub = $service->createPending($student, $this->package($slug), $payer);

        return $service->activate($sub);
    }

    protected function makeAdmin(): User
    {
        $a = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $a->assignRole(Role::ADMIN);

        return $a;
    }

    protected function makeTeacher(): User
    {
        $t = User::factory()->create(['status' => User::STATUS_ACTIVE]);
        $t->assignRole(Role::TEACHER);

        return $t;
    }

    /** Service giữ cache theo request — test gọi nhiều lần trong một process cần instance mới. */
    protected function service(): SubscriptionService
    {
        app()->forgetInstance(SubscriptionService::class);

        return app(SubscriptionService::class);
    }
}
