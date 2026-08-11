<?php

/**
 * DuskTestCase — base class for all Laravel Dusk browser tests.
 *
 * WHAT IS DUSK?
 * Laravel Dusk is a browser automation library built on top of ChromeDriver
 * and the W3C WebDriver protocol. It lets you write browser tests in PHP
 * that control a real Chromium browser — clicking links, filling forms,
 * asserting what's visible on screen — without leaving the Laravel ecosystem.
 *
 * HOW DUSK DIFFERS FROM FEATURE TESTS
 * Feature tests (tests/Feature/) send HTTP requests directly inside PHP with
 * no browser involved. They are fast but cannot test JavaScript, real session
 * cookies, or multi-step user flows that depend on browser state.
 * Dusk tests launch a real browser via ChromeDriver, hit a running HTTP
 * server (`php artisan serve`), and exercise the full stack including JS.
 *
 * HOW DUSK DIFFERS FROM PLAYWRIGHT
 * Playwright (e2e/) does the same job but is written in TypeScript and runs
 * via Node. The test scenarios in tests/Browser/ deliberately mirror those
 * in e2e/posts.spec.ts so you can compare the two APIs side-by-side.
 * Dusk is the PHP-native choice; Playwright is the JS ecosystem choice.
 *
 * SETUP
 * 1. Ensure ChromeDriver matches your installed Chrome version.
 *    `php artisan dusk:chrome-driver` installs the matching binary.
 * 2. Seed the real SQLite database (not :memory:):
 *    `npm run dusk:fresh`   (alias for php artisan migrate:fresh --seed --env=dusk)
 * 3. Run the suite:
 *    `npm run test:dusk`    (alias for php artisan dusk)
 */

namespace Tests;

use Facebook\WebDriver\Chrome\ChromeOptions;
use Facebook\WebDriver\Remote\DesiredCapabilities;
use Facebook\WebDriver\Remote\RemoteWebDriver;
use Illuminate\Support\Collection;
use Laravel\Dusk\TestCase as BaseTestCase;
use PHPUnit\Framework\Attributes\BeforeClass;

abstract class DuskTestCase extends BaseTestCase
{
    /**
     * Prepare for Dusk test execution.
     */
    #[BeforeClass]
    public static function prepare(): void
    {
        if (! static::runningInSail()) {
            static::startChromeDriver(['--port=9515']);
        }
    }

    /**
     * Create the RemoteWebDriver instance.
     */
    protected function driver(): RemoteWebDriver
    {
        $options = (new ChromeOptions)->addArguments(collect([
            $this->shouldStartMaximized() ? '--start-maximized' : '--window-size=1920,1080',
            '--disable-search-engine-choice-screen',
            '--disable-smooth-scrolling',
        ])->unless($this->hasHeadlessDisabled(), function (Collection $items) {
            return $items->merge([
                '--disable-gpu',
                '--headless=new',
            ]);
        })->all());

        return RemoteWebDriver::create(
            $_ENV['DUSK_DRIVER_URL'] ?? env('DUSK_DRIVER_URL') ?? 'http://localhost:9515',
            DesiredCapabilities::chrome()->setCapability(
                ChromeOptions::CAPABILITY, $options
            )
        );
    }
}
