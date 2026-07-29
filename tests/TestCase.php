<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Feature tests don't run `npm run build`, so there is no Vite
        // manifest on disk. withoutVite() swaps the @vite() directive for
        // a no-op so Blade views render without throwing.
        $this->withoutVite();
    }
}
