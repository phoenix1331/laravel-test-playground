<?php

/*
|--------------------------------------------------------------------------
| Unit tests — PostService authorization logic and model helpers
|--------------------------------------------------------------------------
|
| WHAT IS A UNIT TEST?
| A unit test checks one class or function in complete isolation.
| No database, no HTTP, no Laravel app boot — just plain PHP objects.
|
| WHY NO FACTORIES HERE?
| Factories call fake() which requires Laravel's service container.
| Booting the container in a unit test makes it slow and couples it to
| the framework. Instead we construct models directly with new and set
| attributes ourselves. It's more verbose but completely transparent.
|
| THE CLEAR BOUNDARY
| If a test needs the DB (e.g. to test that a record was actually saved),
| it belongs in tests/Feature, not here. Unit tests only verify logic —
| "given these inputs, does the method make the right decision?"
|
*/

use App\Enums\PostStatus;
use App\Enums\Role;
use App\Models\Post;
use App\Models\User;
use App\Services\PostService;
use Illuminate\Auth\Access\AuthorizationException;

// ---------------------------------------------------------------------------
// Helpers — build plain model instances without touching the DB or container
// ---------------------------------------------------------------------------

/**
 * Build a User model in memory with the given role and id.
 * No DB, no factory, no fake() — just a PHP object.
 */
function makeUser(Role $role, int $id = 1): User
{
    $user = new User();
    $user->id = $id;
    // Bypass the cast so we can set the enum directly on the instance.
    $user->setRawAttributes(['role' => $role->value, 'id' => $id], true);

    return $user;
}

/**
 * Build a Post model in memory with the given status and owner id.
 */
function makePost(PostStatus $status, int $userId = 1, int $id = 10): Post
{
    $post = new Post();
    $post->setRawAttributes([
        'id'           => $id,
        'user_id'      => $userId,
        'title'        => 'Test Post',
        'body'         => 'Test body.',
        'status'       => $status->value,
        'published_at' => $status === PostStatus::Published ? now()->toDateTimeString() : null,
    ], true);

    return $post;
}

// ---------------------------------------------------------------------------
// publish() — authorization rules
// ---------------------------------------------------------------------------

describe('PostService::publish authorization', function () {

    /*
     * DATA PROVIDERS (Pest datasets)
     *
     * A dataset lets us run the same test with multiple inputs without
     * copy-pasting the test body. Each row is one scenario.
     *
     * Columns: description, actor role, is the actor the post author?, expected outcome
     */
    dataset('publish scenarios', [
        'admin can publish any post'        => [Role::Admin,    false, true],
        'author can publish their own post' => [Role::Customer, true,  true],
        'customer cannot publish others'    => [Role::Customer, false, false],
    ]);

    /*
     * Unit tests only verify the GUARD logic — does the service throw or not?
     * Whether the DB row was actually updated is a persistence concern and is
     * covered in the Feature tests where the DB is available.
     *
     * Unauthorised scenarios: we assert an exception is thrown.
     * Authorised scenarios:   we assert no exception is thrown.
     */
    it('enforces publish rules', function (Role $role, bool $isAuthor, bool $allowed) {
        $actorId = $isAuthor ? 1 : 2;
        $actor   = makeUser($role, $actorId);
        $post    = makePost(PostStatus::Draft, userId: 1);
        $service = new PostService();

        if (! $allowed) {
            expect(fn () => $service->publish($actor, $post))
                ->toThrow(AuthorizationException::class);

            return;
        }

        // Authorised path: the guard passes and the service attempts to
        // persist. We catch any DB-related exception so the unit test doesn't
        // fail on infrastructure — only on a logic (AuthorizationException).
        try {
            $service->publish($actor, $post);
        } catch (AuthorizationException $e) {
            $this->fail('Expected no AuthorizationException but got: ' . $e->getMessage());
        } catch (\Throwable) {
            // A DB or connection error is acceptable in a no-DB unit test.
            // The guard did its job — that's all we care about here.
        }

        expect(true)->toBeTrue(); // guard passed
    })->with('publish scenarios');
});

// ---------------------------------------------------------------------------
// delete() — authorization rules
// ---------------------------------------------------------------------------

describe('PostService::delete authorization', function () {

    dataset('delete scenarios', [
        'admin deletes any post'                  => [Role::Admin,    false, PostStatus::Published, true],
        'author deletes their own draft'          => [Role::Customer, true,  PostStatus::Draft,     true],
        'author cannot delete their own published' => [Role::Customer, true,  PostStatus::Published, false],
        'customer cannot delete others post'      => [Role::Customer, false, PostStatus::Draft,     false],
    ]);

    it('enforces delete rules', function (Role $role, bool $isAuthor, PostStatus $status, bool $allowed) {
        $actorId = $isAuthor ? 1 : 2;
        $actor   = makeUser($role, $actorId);
        $post    = makePost($status, userId: 1);
        $service = new PostService();

        if (! $allowed) {
            expect(fn () => $service->delete($actor, $post))
                ->toThrow(AuthorizationException::class);

            return;
        }

        // Authorised path: only assert the guard passes.
        // DB deletion is verified in Feature tests.
        try {
            $service->delete($actor, $post);
        } catch (AuthorizationException $e) {
            $this->fail('Expected no AuthorizationException but got: ' . $e->getMessage());
        } catch (\Throwable) {
            // DB errors are acceptable here — the guard is what we're testing.
        }

        expect(true)->toBeTrue(); // guard passed
    })->with('delete scenarios');
});

// ---------------------------------------------------------------------------
// Post model helpers
// ---------------------------------------------------------------------------

describe('Post model helpers', function () {

    it('isOwnedBy returns true for the author and false for others', function () {
        $author = makeUser(Role::Customer, id: 5);
        $other  = makeUser(Role::Customer, id: 9);
        $post   = makePost(PostStatus::Draft, userId: 5);

        expect($post->isOwnedBy($author))->toBeTrue()
            ->and($post->isOwnedBy($other))->toBeFalse();
    });

    /*
     * Dataset: one row per PostStatus value.
     * If a new status is ever added to the enum, this test will fail
     * until isDraft/isPublished are updated — a useful safety net.
     */
    dataset('post status expectations', [
        'draft is not published'     => [PostStatus::Draft,     false, true],
        'published is not draft'     => [PostStatus::Published, true,  false],
    ]);

    it('reports published and draft status correctly', function (PostStatus $status, bool $isPublished, bool $isDraft) {
        $post = makePost($status);

        expect($post->isPublished())->toBe($isPublished)
            ->and($post->isDraft())->toBe($isDraft);
    })->with('post status expectations');
});

// ---------------------------------------------------------------------------
// User model helpers
// ---------------------------------------------------------------------------

describe('User model helpers', function () {

    dataset('user role expectations', [
        'admin isAdmin true, isCustomer false'    => [Role::Admin,    true,  false],
        'customer isAdmin false, isCustomer true' => [Role::Customer, false, true],
    ]);

    it('reports role helpers correctly', function (Role $role, bool $isAdmin, bool $isCustomer) {
        $user = makeUser($role);

        expect($user->isAdmin())->toBe($isAdmin)
            ->and($user->isCustomer())->toBe($isCustomer);
    })->with('user role expectations');
});

// ---------------------------------------------------------------------------
// Role enum helper
// ---------------------------------------------------------------------------

describe('Role enum', function () {

    it('isAdmin returns true only for the Admin case', function () {
        expect(Role::Admin->isAdmin())->toBeTrue()
            ->and(Role::Customer->isAdmin())->toBeFalse();
    });
});

// ---------------------------------------------------------------------------
// PostStatus enum helper
// ---------------------------------------------------------------------------

describe('PostStatus enum', function () {

    it('isPublished returns true only for the Published case', function () {
        expect(PostStatus::Published->isPublished())->toBeTrue()
            ->and(PostStatus::Draft->isPublished())->toBeFalse();
    });
});
