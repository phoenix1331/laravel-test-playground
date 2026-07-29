<?php

/*
|--------------------------------------------------------------------------
| Feature tests — Post web routes (Blade / redirects)
|--------------------------------------------------------------------------
|
| These tests cover the server-rendered web routes. They assert on:
|   - HTTP status codes (200, 302, etc.)
|   - Redirect destinations and flash messages
|   - Text present in the HTML response
|   - Database state after a form submission
|
| They do NOT test JavaScript, CSS, or anything the browser renders
| client-side. For that, see the Playwright e2e tests in /e2e.
|
*/

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;

// ---------------------------------------------------------------------------
// GET /posts — public index
// ---------------------------------------------------------------------------

describe('GET /posts', function () {

    it('is publicly accessible', function () {
        $this->get('/posts')->assertOk();
    });

    it('displays published posts', function () {
        $post = Post::factory()->published()->create(['title' => 'A Visible Post']);

        $this->get('/posts')
            ->assertOk()
            ->assertSee('A Visible Post');
    });

    it('does not display draft posts', function () {
        Post::factory()->draft()->create(['title' => 'A Hidden Draft']);

        $this->get('/posts')
            ->assertOk()
            ->assertDontSee('A Hidden Draft');
    });
});

// ---------------------------------------------------------------------------
// GET /posts/create — create form
// ---------------------------------------------------------------------------

describe('GET /posts/create', function () {

    it('redirects guests to login', function () {
        $this->get('/posts/create')
            ->assertRedirect('/login');
    });

    it('shows the create form to authenticated users', function () {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->get('/posts/create')
            ->assertOk()
            ->assertSee('New Post');
    });
});

// ---------------------------------------------------------------------------
// POST /posts — store
// ---------------------------------------------------------------------------

describe('POST /posts', function () {

    it('redirects guests to login', function () {
        $this->post('/posts', ['title' => 'Test', 'body' => 'Test body content here.'])
            ->assertRedirect('/login');
    });

    it('creates a draft and redirects to the posts index', function () {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->post('/posts', [
                'title' => 'Brand New Post',
                'body'  => 'This is the body of the brand new post.',
            ])
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseHas('posts', [
            'title'   => 'Brand New Post',
            'user_id' => $user->id,
            'status'  => PostStatus::Draft->value,
        ]);
    });

    /*
     * Dataset: one row per invalid submission scenario.
     * The web form should redirect back with validation errors in the session,
     * not return a 422 like the API — that's a key difference between web and
     * API responses to the same validation failure.
     */
    dataset('invalid web submissions', [
        'empty title'    => [['body' => 'Long enough body text.'], 'title'],
        'short title'    => [['title' => 'Hi', 'body' => 'Long enough body text.'], 'title'],
        'empty body'     => [['title' => 'Valid Title'], 'body'],
        'body too short' => [['title' => 'Valid Title', 'body' => 'Too short'], 'body'],
    ]);

    it('redirects back with validation errors on bad input', function (array $data, string $errorField) {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->post('/posts', $data)
            ->assertRedirect()
            ->assertSessionHasErrors($errorField);
    })->with('invalid web submissions');
});

// ---------------------------------------------------------------------------
// PATCH /posts/{post}/publish — publish
// ---------------------------------------------------------------------------

describe('PATCH /posts/{post}/publish', function () {

    it('redirects guests to login', function () {
        $post = Post::factory()->draft()->create();

        $this->patch(route('posts.publish', $post))
            ->assertRedirect('/login');
    });

    it('lets an author publish their own draft and redirects with success', function () {
        $author = User::factory()->customer()->create();
        $post   = Post::factory()->draft()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->patch(route('posts.publish', $post))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success', 'Post published.');

        $this->assertDatabaseHas('posts', [
            'id'     => $post->id,
            'status' => PostStatus::Published->value,
        ]);
    });

    it('redirects with an error when a customer tries to publish another users post', function () {
        $owner    = User::factory()->customer()->create();
        $attacker = User::factory()->customer()->create();
        $post     = Post::factory()->draft()->create(['user_id' => $owner->id]);

        $this->actingAs($attacker)
            ->patch(route('posts.publish', $post))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('error');

        // Post must remain a draft.
        $this->assertDatabaseHas('posts', [
            'id'     => $post->id,
            'status' => PostStatus::Draft->value,
        ]);
    });
});

// ---------------------------------------------------------------------------
// DELETE /posts/{post} — destroy
// ---------------------------------------------------------------------------

describe('DELETE /posts/{post}', function () {

    it('redirects guests to login', function () {
        $post = Post::factory()->draft()->create();

        $this->delete(route('posts.destroy', $post))
            ->assertRedirect('/login');
    });

    it('lets an author delete their own draft', function () {
        $author = User::factory()->customer()->create();
        $post   = Post::factory()->draft()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success', 'Post deleted.');

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    });

    it('lets an admin delete any post', function () {
        $admin = User::factory()->admin()->create();
        $post  = Post::factory()->published()->create();

        $this->actingAs($admin)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    });

    it('prevents a customer from deleting another users post', function () {
        $owner    = User::factory()->customer()->create();
        $attacker = User::factory()->customer()->create();
        $post     = Post::factory()->draft()->create(['user_id' => $owner->id]);

        $this->actingAs($attacker)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    });

    it('prevents a customer from deleting their own published post', function () {
        $author = User::factory()->customer()->create();
        $post   = Post::factory()->published()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->delete(route('posts.destroy', $post))
            ->assertRedirect(route('posts.index'))
            ->assertSessionHas('error');

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    });
});
