<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePostRequest;
use App\Models\Post;
use App\Services\PostService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * PostController — handles both web (HTML) and API (JSON) responses.
 *
 * The controller's only job is to:
 *  1. Translate the HTTP request into arguments the service understands.
 *  2. Call the service.
 *  3. Translate the result into an HTTP response.
 *
 * It contains NO business logic. All rules (who can publish, who can delete)
 * live in PostService. This makes the controller easy to read and the service
 * easy to unit-test without HTTP.
 */
class PostController extends Controller
{
    public function __construct(
        // Laravel injects the service automatically via the container.
        private readonly PostService $postService,
    ) {}

    // -------------------------------------------------------------------------
    // Web routes — return Blade views / redirects
    // -------------------------------------------------------------------------

    /**
     * GET /posts
     * Show all published posts in a Blade view.
     */
    public function index(): View
    {
        $posts = $this->postService->listPublished();

        return view('posts.index', compact('posts'));
    }

    /**
     * GET /posts/create
     * Show the form to write a new post.
     */
    public function create(): View
    {
        return view('posts.create');
    }

    /**
     * POST /posts
     * Validate, create a draft post, redirect back to the list.
     */
    public function store(StorePostRequest $request): RedirectResponse
    {
        $this->postService->create($request->user(), $request->validated());

        return redirect()->route('posts.index')
            ->with('success', 'Post created as a draft.');
    }

    /**
     * PATCH /posts/{post}/publish
     * Publish a post — only the author or an admin may do this.
     */
    public function publish(Request $request, Post $post): RedirectResponse
    {
        try {
            $this->postService->publish($request->user(), $post);
        } catch (AuthorizationException $e) {
            return redirect()->route('posts.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('posts.index')
            ->with('success', 'Post published.');
    }

    /**
     * DELETE /posts/{post}
     * Delete a post — rules enforced by the service.
     */
    public function destroy(Request $request, Post $post): RedirectResponse
    {
        try {
            $this->postService->delete($request->user(), $post);
        } catch (AuthorizationException $e) {
            return redirect()->route('posts.index')
                ->with('error', $e->getMessage());
        }

        return redirect()->route('posts.index')
            ->with('success', 'Post deleted.');
    }

    // -------------------------------------------------------------------------
    // API routes — return JSON
    // -------------------------------------------------------------------------

    /**
     * GET /api/posts
     * Return all published posts as JSON.
     */
    public function apiIndex(): JsonResponse
    {
        $posts = $this->postService->listPublished();

        return response()->json($posts);
    }

    /**
     * POST /api/posts
     * Create a draft post, return the new resource as JSON.
     */
    public function apiStore(StorePostRequest $request): JsonResponse
    {
        $post = $this->postService->create($request->user(), $request->validated());

        return response()->json($post, 201);
    }

    /**
     * GET /api/posts/{post}
     * Return a single post as JSON (published only).
     */
    public function apiShow(Post $post): JsonResponse
    {
        if ($post->isDraft()) {
            return response()->json(['message' => 'Post not found.'], 404);
        }

        return response()->json($post->load('author'));
    }

    /**
     * PATCH /api/posts/{post}/publish
     * Publish a post via the API.
     */
    public function apiPublish(Request $request, Post $post): JsonResponse
    {
        try {
            $post = $this->postService->publish($request->user(), $post);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json($post);
    }

    /**
     * DELETE /api/posts/{post}
     * Delete a post via the API.
     */
    public function apiDestroy(Request $request, Post $post): JsonResponse
    {
        try {
            $this->postService->delete($request->user(), $post);
        } catch (AuthorizationException $e) {
            return response()->json(['message' => $e->getMessage()], 403);
        }

        return response()->json(['message' => 'Post deleted.']);
    }
}
