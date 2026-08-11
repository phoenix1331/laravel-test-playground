<?php

/**
 * Browser tests — Creating a post
 *
 * These tests cover the /posts/create form: filling it out, submitting it,
 * and the validation errors that appear when required fields are missing or
 * too short. They are the Dusk equivalent of the 'create post' describe
 * block in e2e/posts.spec.ts.
 *
 * PLAYWRIGHT VS DUSK — create post describe block
 *
 *   Playwright (TypeScript)                  Dusk (PHP)
 *   ---------------------------------------- ----------------------------------------
 *   page.goto('/posts/create')               $browser->visit('/posts/create')
 *   page.fill('#title', 'My Post')           ->type('#title', 'My Post')
 *   page.fill('#body', 'Body text')          ->type('#body', 'Body text')
 *   page.click('.save-draft-btn')            ->click('.save-draft-btn')
 *   expect(page).toHaveURL(/\/posts/)        ->assertPathIs('/posts')
 *   expect(locator('.flash-success'))        ->assertSee('Post created as a draft')
 *   expect(page).toHaveURL(/\/posts\/create) ->assertPathIs('/posts/create')
 *   expect(locator('.errors')).toBeVisible() ->assertPresent('.errors')
 *   expect(page).toHaveURL(/\/login/)        ->assertPathIs('/login')
 *
 * SETUP
 *   npm run dusk:fresh   # seed the real SQLite database
 *   npm run test:dusk    # run the full Dusk suite
 */

use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\InteractsWithAuth;

// ---------------------------------------------------------------------------
// Happy path — save a draft
// ---------------------------------------------------------------------------

test('customer can fill the form and save a draft', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'customer@example.com');

        $browser->visit('/posts/create')
                ->assertSeeIn('h1', 'New Post')
                ->type('#title', 'My Dusk Post')
                ->type('#body', 'This post was created by a Dusk browser test.')
                ->click('.save-draft-btn')
                // After saving, redirected to the index with a success flash.
                ->assertPathIs('/posts')
                ->assertSee('Post created as a draft');
    });
})->uses(InteractsWithAuth::class);

// ---------------------------------------------------------------------------
// Validation — missing title
// ---------------------------------------------------------------------------

test('shows validation errors when title is missing', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'customer@example.com');

        $browser->visit('/posts/create')
                ->type('#body', 'Body text is here but title is missing.')
                ->click('.save-draft-btn')
                // Form re-renders with errors — must not navigate away.
                ->assertPathIs('/posts/create')
                ->assertPresent('.errors');
    });
})->uses(InteractsWithAuth::class);

// ---------------------------------------------------------------------------
// Validation — body too short
// ---------------------------------------------------------------------------

test('shows validation errors when body is too short', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'customer@example.com');

        $browser->visit('/posts/create')
                ->type('#title', 'Valid Title Here')
                ->type('#body', 'Too short')
                ->click('.save-draft-btn')
                ->assertPathIs('/posts/create')
                ->assertPresent('.errors');
    });
})->uses(InteractsWithAuth::class);

// ---------------------------------------------------------------------------
// Guest redirect
// ---------------------------------------------------------------------------

test('guest is redirected to login when visiting create page', function () {
    $this->browse(function (Browser $browser) {
        // No login — visit the protected route directly.
        $browser->visit('/posts/create')
                ->assertPathIs('/login');
    });
});
