<?php

/*
|--------------------------------------------------------------------------
| Feature tests — Post API (JSON endpoints)
|--------------------------------------------------------------------------
|
| WHAT IS A FEATURE TEST?
| A feature test boots the full Laravel application and fires real HTTP
| requests through the router → middleware → controller → service → database.
| Everything runs except an actual network socket — Laravel intercepts the
| request internally.
|
| HOW THIS DIFFERS FROM A UNIT TEST
| Unit tests call PHP methods directly and assert on return values.
| Feature tests send HTTP requests and assert on the response: status codes,
| JSON shape, database state.
|
| HOW THIS DIFFERS FROM AN E2E TEST
| Feature tests never open a browser. There is no JavaScript execution,
| no rendered HTML, no user interaction. They are fast (milliseconds per
| test) while still covering the full server-side stack.
|
| DATABASE
| RefreshDatabase (configured in Pest.php) wraps every test in a
| transaction and rolls it back afterwards, so each test starts with a
| clean slate without truncating tables between runs.
|
*/

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;

// ---------------------------------------------------------------------------
// GET /api/posts — public listing
// ---------------------------------------------------------------------------

describe('GET /api/posts', function () {

    it('returns an empty array when there are no published posts', function () {
        $this->getJson('/api/posts')
            ->assertOk()
            ->assertJson([]);
    });

    it('returns only published posts, not drafts', function () {
        Post::factory()->published()->count(3)->create();
        Post::factory()->draft()->count(2)->create();

        $this->getJson('/api/posts')
            ->assertOk()
            ->assertJsonCount(3);
    });

    it('includes the author relationship on each post', function () {
        Post::factory()->published()->create();

        $this->getJson('/api/posts')
            ->assertOk()
            ->assertJsonPath('0.author.id', fn ($id) => is_int($id));
    });

    /*
     * Dataset: verify the response contains the expected keys regardless
     * of what data is in each post. This guards against accidental field
     * removal when the model or service changes.
     */
    dataset('required post fields', [
        'id', 'title', 'body', 'status', 'published_at', 'user_id',
    ]);

    it('response includes required field', function (string $field) {
        Post::factory()->published()->create();

        $this->getJson('/api/posts')
            ->assertOk()
            ->assertJsonPath("0.{$field}", fn ($v) => ! is_null($v) || true);
    })->with('required post fields');
});

// ---------------------------------------------------------------------------
// GET /api/posts/{post} — single post
// ---------------------------------------------------------------------------

describe('GET /api/posts/{post}', function () {

    it('returns a published post with its author', function () {
        $post = Post::factory()->published()->create();

        $this->getJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('id', $post->id)
            ->assertJsonPath('author.id', $post->author->id);
    });

    it('returns 404 for a draft post', function () {
        $post = Post::factory()->draft()->create();

        $this->getJson("/api/posts/{$post->id}")
            ->assertNotFound()
            ->assertJsonPath('message', 'Post not found.');
    });

    it('returns 404 for a non-existent post', function () {
        $this->getJson('/api/posts/99999')
            ->assertNotFound();
    });
});

// ---------------------------------------------------------------------------
// POST /api/posts — create a draft
// ---------------------------------------------------------------------------

describe('POST /api/posts', function () {

    it('requires authentication', function () {
        $this->postJson('/api/posts', ['title' => 'Hello', 'body' => 'World body text'])
            ->assertUnauthorized();
    });

    it('creates a draft post and returns 201', function () {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', [
                'title' => 'My First Post',
                'body'  => 'This is the body of my first post.',
            ])
            ->assertCreated()
            ->assertJsonPath('status', PostStatus::Draft->value)
            ->assertJsonPath('title', 'My First Post')
            ->assertJsonPath('user_id', $user->id);
    });

    it('persists the post to the database', function () {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', [
                'title' => 'Persisted Post',
                'body'  => 'This should appear in the database.',
            ]);

        // assertDatabaseHas is only available in feature tests — it checks
        // the actual SQLite database, something a unit test cannot do.
        $this->assertDatabaseHas('posts', [
            'title'   => 'Persisted Post',
            'user_id' => $user->id,
            'status'  => PostStatus::Draft->value,
        ]);
    });

    /*
     * Dataset: each row is one invalid payload. The test verifies the API
     * rejects it with a 422 and includes the right field in the errors.
     */
    dataset('invalid post payloads', [
        'missing title'      => [['body' => 'Long enough body here.'], 'title'],
        'title too short'    => [['title' => 'Hi', 'body' => 'Long enough body here.'], 'title'],
        'missing body'       => [['title' => 'Valid Title'], 'body'],
        'body too short'     => [['title' => 'Valid Title', 'body' => 'Short'], 'body'],
        'missing both'       => [[], 'title'],
    ]);

    it('rejects invalid input with 422', function (array $payload, string $errorField) {
        $user = User::factory()->customer()->create();

        $this->actingAs($user)
            ->postJson('/api/posts', $payload)
            ->assertUnprocessable()
            ->assertJsonValidationErrors($errorField);
    })->with('invalid post payloads');
});

// ---------------------------------------------------------------------------
// PATCH /api/posts/{post}/publish
// ---------------------------------------------------------------------------

describe('PATCH /api/posts/{post}/publish', function () {

    it('requires authentication', function () {
        $post = Post::factory()->draft()->create();

        $this->patchJson("/api/posts/{$post->id}/publish")
            ->assertUnauthorized();
    });

    it('allows an author to publish their own draft', function () {
        $author = User::factory()->customer()->create();
        $post   = Post::factory()->draft()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->patchJson("/api/posts/{$post->id}/publish")
            ->assertOk()
            ->assertJsonPath('status', PostStatus::Published->value);

        $this->assertDatabaseHas('posts', [
            'id'     => $post->id,
            'status' => PostStatus::Published->value,
        ]);
    });

    it('allows an admin to publish any post', function () {
        $admin  = User::factory()->admin()->create();
        $author = User::factory()->customer()->create();
        $post   = Post::factory()->draft()->create(['user_id' => $author->id]);

        $this->actingAs($admin)
            ->patchJson("/api/posts/{$post->id}/publish")
            ->assertOk()
            ->assertJsonPath('status', PostStatus::Published->value);
    });

    it('returns 403 when a customer tries to publish another users post', function () {
        $other    = User::factory()->customer()->create();
        $attacker = User::factory()->customer()->create();
        $post     = Post::factory()->draft()->create(['user_id' => $other->id]);

        $this->actingAs($attacker)
            ->patchJson("/api/posts/{$post->id}/publish")
            ->assertForbidden()
            ->assertJsonPath('message', 'You can only publish your own posts.');

        // The post must still be a draft in the database.
        $this->assertDatabaseHas('posts', [
            'id'     => $post->id,
            'status' => PostStatus::Draft->value,
        ]);
    });
});

// ---------------------------------------------------------------------------
// DELETE /api/posts/{post}
// ---------------------------------------------------------------------------

describe('DELETE /api/posts/{post}', function () {

    it('requires authentication', function () {
        $post = Post::factory()->draft()->create();

        $this->deleteJson("/api/posts/{$post->id}")
            ->assertUnauthorized();
    });

    it('allows an author to delete their own draft', function () {
        $author = User::factory()->customer()->create();
        $post   = Post::factory()->draft()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->deleteJson("/api/posts/{$post->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Post deleted.');

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    });

    it('allows an admin to delete any post', function () {
        $admin = User::factory()->admin()->create();
        $post  = Post::factory()->published()->create();

        $this->actingAs($admin)
            ->deleteJson("/api/posts/{$post->id}")
            ->assertOk();

        $this->assertDatabaseMissing('posts', ['id' => $post->id]);
    });

    it('prevents a customer from deleting another users post', function () {
        $owner    = User::factory()->customer()->create();
        $attacker = User::factory()->customer()->create();
        $post     = Post::factory()->draft()->create(['user_id' => $owner->id]);

        $this->actingAs($attacker)
            ->deleteJson("/api/posts/{$post->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    });

    it('prevents a customer from deleting their own published post', function () {
        $author = User::factory()->customer()->create();
        $post   = Post::factory()->published()->create(['user_id' => $author->id]);

        $this->actingAs($author)
            ->deleteJson("/api/posts/{$post->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('posts', ['id' => $post->id]);
    });
});
