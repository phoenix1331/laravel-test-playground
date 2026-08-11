<?php

// Every test inside tests/Browser/ gets DuskTestCase as its base, which
// wires up ChromeDriver and the Dusk browser() helper. We do NOT attach
// RefreshDatabase here — Dusk tests run against a real HTTP server and a
// real file-based SQLite database seeded via `npm run dusk:fresh`.
pest()->extend(Tests\DuskTestCase::class)
    ->in('Browser');

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Pest.php — global test configuration
|--------------------------------------------------------------------------
|
| This file is loaded before every test suite. Use it to:
|   • bind the default test-case class for each directory
|   • add shared traits (RefreshDatabase, WithFaker, etc.)
|   • register custom expectation helpers used across multiple test files
|
*/

// Every test inside tests/Feature/ gets Laravel's TestCase so it can boot
// the app, use $this->get(), actingAs(), etc.
// RefreshDatabase wraps each test in a transaction and rolls it back, so
// the database is always clean at the start of every feature test.
pest()
    ->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

// Unit tests extend bare PHPUnit\Framework\TestCase by default (set in
// phpunit.xml). That means no Laravel app boots — which is intentional:
// unit tests should be fast and framework-agnostic.

/*
|--------------------------------------------------------------------------
| Custom expectations
|--------------------------------------------------------------------------
|
| extend() lets you add domain-specific assertion vocabulary.
| These are available in ALL test files.
|
*/

// Example: expect($price)->toBePositive()
expect()->extend('toBePositive', function () {
    return $this->toBeGreaterThan(0);
});
