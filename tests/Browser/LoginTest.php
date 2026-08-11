<?php

/**
 * Browser tests — Authentication (login / logout)
 *
 * These tests cover the login form and the session state it creates.
 * They are the Dusk equivalent of the 'login' describe block in
 * e2e/posts.spec.ts.
 *
 * PLAYWRIGHT VS DUSK — login describe block
 *
 *   Playwright (TypeScript)                    Dusk (PHP)
 *   ------------------------------------------ ------------------------------------------
 *   await loginAs(page, 'user@example.com')    $this->loginAs($browser, 'user@example.com')
 *   await expect(page).toHaveURL(/\/posts/)    ->assertPathIs('/posts')
 *   await expect(page).toHaveURL(/\/login/)    ->assertPathIs('/login')
 *   locator('.errors li').toBeVisible()        ->assertPresent('.errors li')
 *   locator('a', {hasText:'New Post'})         ->assertSeeLink('New Post')
 *
 * NOTE ON SESSION STATE
 * Unlike Playwright which creates a fresh browser context per test by default,
 * Dusk shares one browser instance per test class. Each test here calls
 * loginAs() from scratch, which navigates to /login and submits the form,
 * so session state from a previous test does not carry over.
 *
 * SETUP
 *   npm run dusk:fresh   # seed the real SQLite database
 *   npm run test:dusk    # run the full Dusk suite
 */

use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\InteractsWithAuth;

// ---------------------------------------------------------------------------
// Valid login
// ---------------------------------------------------------------------------

test('redirects to posts index on valid credentials', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'customer@example.com');

        $browser->assertPathIs('/posts');
    });
})->uses(InteractsWithAuth::class);

// ---------------------------------------------------------------------------
// Invalid login
// ---------------------------------------------------------------------------

test('shows an error on invalid credentials', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/login')
                ->type('#email', 'wrong@example.com')
                ->type('#password', 'wrong')
                ->press('button[type=submit]')
                // The form re-renders with errors — it must not redirect away.
                ->assertPathIs('/login')
                ->assertPresent('.errors li, [class*=error]');
    });
});

// ---------------------------------------------------------------------------
// Post-login UI state
// ---------------------------------------------------------------------------

test('shows the New Post link after logging in', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'customer@example.com');

        // The nav bar should now expose the "New Post" link that guests cannot see.
        $browser->assertSeeLink('New Post');
    });
})->uses(InteractsWithAuth::class);
