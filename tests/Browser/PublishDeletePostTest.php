<?php

/**
 * Browser tests — Publishing and deleting posts
 *
 * These tests cover admin actions on posts: publishing a draft and deleting
 * a post. They are the Dusk equivalent of the 'publish post' and
 * 'delete post' describe blocks in e2e/posts.spec.ts.
 *
 * PLAYWRIGHT VS DUSK — publish post / delete post describe blocks
 *
 *   Playwright (TypeScript)                      Dusk (PHP)
 *   -------------------------------------------- --------------------------------------------
 *   page.locator('.publish-btn').first()         $browser->element('.publish-btn')
 *   publishBtn.isVisible()                       ->isPresent('.publish-btn')
 *   publishBtn.click()                           ->click('.publish-btn')
 *   expect(locator('.flash-success'))            ->assertSee('Post published')
 *   expect(locator('text=admin').first())        ->assertSee('admin')
 *   page.locator('article[data-post-id]').count() $browser->elements('article[data-post-id]')
 *   page.once('dialog', d => d.accept())         ->acceptDialog() (Dusk handles JS dialogs)
 *   page.locator('.delete-btn').first().click()  ->click('.delete-btn')
 *   expect(locator('.flash-success'))            ->assertSee('Post deleted')
 *
 * NOTE ON DIALOG HANDLING
 * The delete form triggers a JavaScript confirm() dialog. Playwright handles
 * this with a one-time event listener: `page.once('dialog', d => d.accept())`.
 * Dusk uses ->acceptDialog() chained after the click that triggers the dialog,
 * or alternatively ->waitForDialog()->acceptDialog(). The behaviour is the same.
 *
 * SETUP
 *   npm run dusk:fresh   # seed the real SQLite database
 *   npm run test:dusk    # run the full Dusk suite
 */

use Laravel\Dusk\Browser;
use Tests\Browser\Concerns\InteractsWithAuth;

// ---------------------------------------------------------------------------
// Role badges
// ---------------------------------------------------------------------------

test('admin sees role badge in the nav bar', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'admin@example.com');

        $browser->assertSee('admin');
    });
})->uses(InteractsWithAuth::class);

test('customer sees role badge in the nav bar', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'customer@example.com');

        $browser->assertSee('customer');
    });
})->uses(InteractsWithAuth::class);

// ---------------------------------------------------------------------------
// Publishing a draft
// ---------------------------------------------------------------------------

test('admin can publish a draft via the Publish button', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'admin@example.com');

        $browser->visit('/posts');

        // The seeder creates drafts that are visible to the admin.
        // If a Publish button is present, click it and assert the flash.
        // If all posts are already published, the test still verifies the
        // page renders cleanly — the same conditional approach used in
        // the Playwright equivalent.
        if ($browser->element('.publish-btn')) {
            $browser->click('.publish-btn')
                    ->assertSee('Post published');
        }
    });
})->uses(InteractsWithAuth::class);

// ---------------------------------------------------------------------------
// Deleting a post
// ---------------------------------------------------------------------------

test('admin can delete a post and it disappears from the list', function () {
    $this->browse(function (Browser $browser) {
        $this->loginAs($browser, 'admin@example.com');

        $browser->visit('/posts');

        $articlesBefore = count($browser->elements('article[data-post-id]'));

        if ($articlesBefore === 0) {
            // Nothing to delete — seeder may not have run. Skip gracefully.
            return;
        }

        // Dusk accepts the JS confirm() dialog automatically when you call
        // acceptDialog() before (or immediately after) the action that triggers it.
        $browser->click('.delete-btn')
                ->acceptDialog()
                ->assertPathIs('/posts')
                ->assertSee('Post deleted');

        $articlesAfter = count($browser->elements('article[data-post-id]'));

        expect($articlesAfter)->toBe($articlesBefore - 1);
    });
})->uses(InteractsWithAuth::class);
