<?php

namespace App\Services;

use App\Enums\PostStatus;
use App\Models\Post;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;

/**
 * PostService — all post business logic lives here, not in the controller.
 *
 * The controller's job is to translate an HTTP request into method arguments
 * and turn the return value into an HTTP response. The service's job is to
 * enforce the rules. This separation means:
 *
 *  - Unit tests can test the rules by calling the service directly, no HTTP needed.
 *  - Feature tests can test the full HTTP flow without duplicating rule assertions.
 */
class PostService
{
    /**
     * Create a new draft post owned by the given user.
     *
     * Any authenticated user (admin or customer) may create a post.
     */
    public function create(User $author, array $data): Post
    {
        return $author->posts()->create([
            'title'  => $data['title'],
            'body'   => $data['body'],
            'status' => PostStatus::Draft,
        ]);
    }

    /**
     * Publish a post.
     *
     * Rules:
     *  - Admins can publish any post.
     *  - Customers can only publish their own posts.
     *
     * @throws AuthorizationException
     */
    public function publish(User $actor, Post $post): Post
    {
        if (! $actor->isAdmin() && ! $post->isOwnedBy($actor)) {
            throw new AuthorizationException('You can only publish your own posts.');
        }

        $post->update([
            'status'       => PostStatus::Published,
            'published_at' => now(),
        ]);

        return $post->fresh();
    }

    /**
     * Delete a post.
     *
     * Rules:
     *  - Admins can delete any post.
     *  - Customers can only delete their own posts, and only while still a draft.
     *
     * @throws AuthorizationException
     */
    public function delete(User $actor, Post $post): void
    {
        if ($actor->isAdmin()) {
            $post->delete();
            return;
        }

        if (! $post->isOwnedBy($actor)) {
            throw new AuthorizationException('You can only delete your own posts.');
        }

        if ($post->isPublished()) {
            throw new AuthorizationException('You cannot delete a published post.');
        }

        $post->delete();
    }

    /**
     * Return all published posts, newest first.
     *
     * No authorization needed — published posts are public.
     *
     * @return Collection<int, Post>
     */
    public function listPublished(): Collection
    {
        return Post::where('status', PostStatus::Published)
            ->with('author')
            ->latest('published_at')
            ->get();
    }

    /**
     * Return all posts belonging to the given user, newest first.
     *
     * @return Collection<int, Post>
     */
    public function listForUser(User $user): Collection
    {
        return $user->posts()->latest()->get();
    }
}
