<?php

namespace Tests\Feature\Infrastructure;

use App\Providers\AppServiceProvider;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class DebugGuardTest extends TestCase
{
    public function test_debug_is_forced_off_in_production(): void
    {
        Log::spy();
        $this->app['env'] = 'production';
        config(['app.debug' => true]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertFalse(config('app.debug'));
        Log::shouldHaveReceived('critical')->once();
    }

    public function test_debug_is_left_alone_outside_production(): void
    {
        config(['app.debug' => true]);

        (new AppServiceProvider($this->app))->boot();

        $this->assertTrue(config('app.debug'));
    }
}
