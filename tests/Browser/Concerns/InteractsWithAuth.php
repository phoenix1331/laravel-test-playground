<?php

namespace Tests\Browser\Concerns;

use Laravel\Dusk\Browser;

/**
 * Shared login helper for Dusk browser tests.
 *
 * Mirrors the loginAs() helper in e2e/posts.spec.ts so the Dusk tests
 * and Playwright tests are a direct apples-to-apples comparison.
 */
trait InteractsWithAuth
{
    /**
     * Log in via the login form and wait for the redirect to /posts.
     */
    protected function loginAs(Browser $browser, string $email, string $password = 'password'): void
    {
        $browser->visit('/login')
                ->type('#email', $email)
                ->type('#password', $password)
                ->press('button[type=submit]')
                ->waitForLocation('/posts');
    }
}
