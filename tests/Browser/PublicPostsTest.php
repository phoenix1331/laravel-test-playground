<?php

/**
 * Browser tests — Public posts index
 *
 * WHAT IS A DUSK BROWSER TEST?
 * Dusk launches a real Chromium browser via ChromeDriver, navigates to a URL,
 * and interacts with the page exactly as a user would — clicking, typing,
 * reading visible text. The full stack is exercised: browser → HTTP →
 * Laravel → database → rendered HTML → back to the browser.
 *
 * HOW THIS DIFFERS FROM A FEATURE TEST
 * Feature tests (tests/Feature/) fire HTTP requests internally with no
 * browser. They cannot test anything that happens client-side: JavaScript,
 * real session cookies, visible flash messages, or multi-step user flows.
 * Dusk tests can test all of that, but they are slower (ChromeDriver must
 * start) and require a running server and a seeded file-based database.
 *
 * HOW THIS RELATES TO THE PLAYWRIGHT SUITE
 * The tests in this file are the PHP/Dusk equivalent of the
 * 'public posts index' describe block in e2e/posts.spec.ts. The scenarios
 * are identical — only the language and API differ:
 *
 *   Playwright (TypeScript)          Dusk (PHP)
 *   -------------------------------- --------------------------------
 *   page.goto('/posts')              $browser->visit('/posts')
 *   expect(page).toHaveTitle(/…/)    ->assertTitleContains('…')
 *   page.locator('h1')               ->assertSeeIn('h1', '…')
 *   expect(locator).toBeVisible()    ->assertPresent('…')
 *   expect(page).not.toHaveText(…)  ->assertDontSee('…')
 *
 * SETUP
 * 1. Seed the real SQLite database (Dusk cannot use :memory:):
 *      npm run dusk:fresh
 * 2. Run just this file:
 *      php artisan dusk tests/Browser/PublicPostsTest.php
 *    Or run the full suite:
 *      npm run test:dusk
 */

use Laravel\Dusk\Browser;

test('shows the posts page with a heading', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/posts')
                ->assertTitleContains('Posts')
                ->assertSeeIn('h1', 'Published Posts');
    });
});

test('shows seeded published posts', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/posts')
                ->assertPresent('article[data-post-id]');
    });
});

test('does not show a New Post link to guests', function () {
    $this->browse(function (Browser $browser) {
        $browser->visit('/posts')
                ->assertDontSee('New Post');
    });
});
