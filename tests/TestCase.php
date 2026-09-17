<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Test kiểm tra hành vi server, không nên phụ thuộc việc đã chạy `npm run build`.
        $this->withoutVite();
    }
}
