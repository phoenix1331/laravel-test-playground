<?php

/**
 * Browser tests — Public posts index
 *
 * These tests cover the /posts page without requiring a logged-in user.
 * They are the Dusk equivalent of the 'public posts index' describe block
 * in e2e/posts.spec.ts.
 *
 * SETUP: php artisan migrate:fresh --seed must be run before this suite.
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
