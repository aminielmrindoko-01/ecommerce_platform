<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests assert HTTP/auth behavior; they do not require compiled Vite assets.
        $this->withoutVite();
    }
}
